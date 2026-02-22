<?php

namespace App\Jobs\Processing;

use App\Models\Document;
use App\Models\ExtractedData;
use App\Models\ProcessingEvent;
use App\Services\DocumentStatusTransitionService;
use App\Services\Processing\ClassificationRoutingResolver;
use App\Services\Processing\OcrProvider;
use App\Services\ProcessingEventRecorder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class OcrExtractionConsumerJob implements ShouldQueue
{
    use Queueable;

    public int $tries;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(public array $payload)
    {
        $this->onConnection('rabbitmq');
        $this->tries = $this->resolveRetryAttempts();
    }

    /**
     * @return list<int>
     */
    public function backoff(): array
    {
        return $this->resolveRetryBackoff();
    }

    public function handle(
        DocumentStatusTransitionService $transitionService,
        ProcessingEventRecorder $processingEventRecorder,
        OcrProvider $ocrProvider,
        ClassificationRoutingResolver $classificationRoutingResolver,
    ): void {
        $documentId = $this->resolveDocumentId();
        $tenantId = $this->resolveTenantId();
        $messageId = $this->resolveMessageId();
        $traceId = $this->resolveTraceId();
        $metadata = $this->resolveMetadata();

        $document = Document::query()
            ->where('tenant_id', $tenantId)
            ->whereKey($documentId)
            ->first();

        if ($document === null) {
            $this->recordTenantMismatch(
                documentId: $documentId,
                tenantId: $tenantId,
                messageId: $messageId,
                traceId: $traceId,
                metadata: $metadata,
                processingEventRecorder: $processingEventRecorder,
            );

            return;
        }

        if ($this->isAlreadyProcessed($document, $messageId)) {
            return;
        }

        $statusBeforeProcessing = (string) $document->status;

        if (in_array($statusBeforeProcessing, ['uploaded', 'scanning'], true)) {
            $this->redispatchWhileScanPending();

            return;
        }

        if ($statusBeforeProcessing === 'scan_failed') {
            return;
        }

        if ($statusBeforeProcessing === 'scan_passed') {
            $document = $transitionService->transition(
                document: $document,
                toStatus: 'extracting',
                consumerName: 'ocr-extraction-transition',
                messageId: (string) Str::uuid(),
                metadata: ['pipeline' => 'ocr-extraction'],
            );
        }

        $document = $document->fresh();

        if ($document === null || ! in_array($document->status, ['extracting', 'classifying', 'ready_for_review'], true)) {
            return;
        }

        $classificationHint = $classificationRoutingResolver->resolveTypeHint(
            is_string($metadata['classification_hint'] ?? null) ? $metadata['classification_hint'] : null,
            $document->file_name,
        );

        if ($document->status === 'extracting') {
            $result = $ocrProvider->extract($document, $this->payload);
            $classificationHint = $classificationRoutingResolver->resolveTypeHint(
                is_string($result['classification_hint'] ?? null) ? $result['classification_hint'] : null,
                $document->file_name,
                is_string($result['extracted_text'] ?? null) ? $result['extracted_text'] : null,
            );

            ExtractedData::query()->updateOrCreate(
                ['document_id' => $document->id],
                [
                    'tenant_id' => $document->tenant_id,
                    'provider' => (string) ($result['provider'] ?? 'simulated'),
                    'extracted_text' => is_string($result['extracted_text'] ?? null)
                        ? $result['extracted_text']
                        : null,
                    'payload' => is_array($result['payload'] ?? null)
                        ? $result['payload']
                        : null,
                    'metadata' => [
                        'classification_hint' => $classificationHint,
                        'provider_metadata' => is_array($result['metadata'] ?? null)
                            ? $result['metadata']
                            : [],
                    ],
                ],
            );

            $document = $transitionService->transition(
                document: $document,
                toStatus: 'classifying',
                consumerName: 'ocr-extraction-transition',
                messageId: (string) Str::uuid(),
                metadata: ['pipeline' => 'ocr-extraction', 'classification_hint' => $classificationHint],
            );
        }

        $classificationPayload = $this->payload;
        $classificationPayload['event'] = 'document.extracted';
        $classificationPayload['metadata'] = array_merge($metadata, [
            'classification_hint' => $classificationHint,
        ]);

        ClassificationConsumerJob::dispatch($classificationPayload)
            ->onConnection('rabbitmq')
            ->onQueue($classificationRoutingResolver->resolveQueueForType($classificationHint));

        $processingEventRecorder->record(
            document: $document,
            messageId: $messageId,
            consumerName: 'ocr-extraction',
            event: 'document.ocr.extracted',
            statusFrom: $statusBeforeProcessing,
            statusTo: (string) $document->status,
            traceId: $traceId,
            metadata: array_merge($metadata, [
                'classification_hint' => $classificationHint,
            ]),
        );
    }

    public function failed(Throwable $exception): void
    {
        DeadLetterConsumerJob::dispatch(
            payload: $this->deadLetterPayload($exception),
            terminalStatus: 'extraction_failed',
        )
            ->onConnection('rabbitmq')
            ->onQueue('queue.dead-letters');
    }

    protected function isAlreadyProcessed(Document $document, string $messageId): bool
    {
        return ProcessingEvent::query()
            ->where('tenant_id', $document->tenant_id)
            ->where('document_id', $document->id)
            ->where('message_id', $messageId)
            ->where('consumer_name', 'ocr-extraction')
            ->exists();
    }

    protected function redispatchWhileScanPending(): void
    {
        $scanWaitAttempt = $this->resolveScanWaitAttempt();
        $retryAttempts = $this->resolveRetryAttempts();

        if ($scanWaitAttempt >= $retryAttempts) {
            throw new RuntimeException('Virus scan did not finish before OCR extraction retries were exhausted.');
        }

        $payload = $this->payload;
        $metadata = $this->resolveMetadata();
        $metadata['scan_wait_attempt'] = $scanWaitAttempt + 1;
        $payload['metadata'] = $metadata;
        $payload['retry_count'] = ((int) ($payload['retry_count'] ?? 0)) + 1;

        self::dispatch($payload)
            ->delay(now()->addSeconds($this->resolveScanWaitDelaySeconds()))
            ->onConnection('rabbitmq')
            ->onQueue('queue.ocr-extraction');
    }

    protected function resolveRetryAttempts(): int
    {
        $configuredAttempts = (int) config('processing.retry_attempts', 3);

        return $configuredAttempts > 0 ? $configuredAttempts : 3;
    }

    /**
     * @return list<int>
     */
    protected function resolveRetryBackoff(): array
    {
        $configuredBackoff = config('processing.retry_backoff', [5, 15, 45]);

        if (! is_array($configuredBackoff)) {
            return [5, 15, 45];
        }

        $backoff = array_values(array_filter(
            array_map(static fn (mixed $value): int => (int) $value, $configuredBackoff),
            static fn (int $value): bool => $value > 0,
        ));

        return $backoff === [] ? [5, 15, 45] : $backoff;
    }

    protected function resolveScanWaitDelaySeconds(): int
    {
        $delay = (int) config('processing.scan_wait_delay_seconds', 5);

        return $delay > 0 ? $delay : 5;
    }

    protected function resolveScanWaitAttempt(): int
    {
        $metadata = $this->resolveMetadata();

        return max(0, (int) ($metadata['scan_wait_attempt'] ?? 0));
    }

    protected function resolveDocumentId(): int
    {
        return (int) ($this->payload['document_id'] ?? 0);
    }

    protected function resolveTenantId(): string
    {
        return (string) ($this->payload['tenant_id'] ?? '');
    }

    protected function resolveMessageId(): string
    {
        $messageId = (string) ($this->payload['message_id'] ?? '');

        return Str::isUuid($messageId)
            ? $messageId
            : (string) Str::uuid();
    }

    protected function resolveTraceId(): string
    {
        $traceId = (string) ($this->payload['trace_id'] ?? '');

        return Str::isUuid($traceId)
            ? $traceId
            : (string) Str::uuid();
    }

    /**
     * @return array<string, mixed>
     */
    protected function resolveMetadata(): array
    {
        return is_array($this->payload['metadata'] ?? null)
            ? $this->payload['metadata']
            : [];
    }

    /**
     * @param  array<string, mixed>  $metadata
     */
    protected function recordTenantMismatch(
        int $documentId,
        string $tenantId,
        string $messageId,
        string $traceId,
        array $metadata,
        ProcessingEventRecorder $processingEventRecorder,
    ): void {
        $document = Document::query()->whereKey($documentId)->first();

        if ($document === null) {
            return;
        }

        $processingEventRecorder->record(
            document: $document,
            messageId: $messageId,
            consumerName: 'ocr-extraction-tenant-mismatch',
            event: 'document.processing.tenant_mismatch',
            statusFrom: $document->status,
            statusTo: $document->status,
            traceId: $traceId,
            metadata: array_merge($metadata, [
                'payload_tenant_id' => $tenantId,
                'resolved_tenant_id' => $document->tenant_id,
            ]),
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function deadLetterPayload(Throwable $exception): array
    {
        $payload = $this->payload;
        $metadata = $this->resolveMetadata();
        $metadata['failed_consumer'] = 'ocr-extraction';
        $metadata['failure_reason'] = $exception->getMessage();
        $payload['metadata'] = $metadata;

        return $payload;
    }
}
