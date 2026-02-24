<?php

namespace App\Jobs\Processing;

use App\Models\Document;
use App\Models\DocumentClassification;
use App\Models\ExtractedData;
use App\Models\ProcessingEvent;
use App\Services\DocumentStatusTransitionService;
use App\Services\Processing\ClassificationProvider;
use App\Services\Processing\ClassificationRoutingResolver;
use App\Services\Processing\ProviderDegradedException;
use App\Services\ProcessingEventRecorder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ClassificationConsumerJob implements ShouldQueue
{
    use Queueable;

    public int $tries;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(public array $payload)
    {
        $this->onConnection($this->resolveQueueConnection());
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
        ClassificationProvider $classificationProvider,
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

        if (in_array($statusBeforeProcessing, ['uploaded', 'scanning', 'scan_passed', 'extracting'], true)) {
            $this->redispatchWhileExtractionPending();

            return;
        }

        if (in_array($statusBeforeProcessing, ['scan_failed', 'extraction_failed', 'classification_failed'], true)) {
            return;
        }

        if ($statusBeforeProcessing === 'ready_for_review') {
            $processingEventRecorder->record(
                document: $document,
                messageId: $messageId,
                consumerName: 'classification',
                event: 'document.classification.already_ready',
                statusFrom: $statusBeforeProcessing,
                statusTo: $statusBeforeProcessing,
                traceId: $traceId,
                metadata: $metadata,
            );

            return;
        }

        if ($statusBeforeProcessing !== 'classifying') {
            return;
        }

        $extractedData = ExtractedData::query()
            ->where('document_id', $document->id)
            ->first();

        try {
            $result = $classificationProvider->classify($document, $extractedData, $this->payload);
        } catch (ProviderDegradedException $exception) {
            $this->redispatchWhileProviderDegraded($exception);

            return;
        }

        $resolvedType = $classificationRoutingResolver->normalizeType(
            is_string($result['type'] ?? null) ? $result['type'] : null,
        ) ?? 'general';

        DocumentClassification::query()->updateOrCreate(
            ['document_id' => $document->id],
            [
                'tenant_id' => $document->tenant_id,
                'provider' => (string) ($result['provider'] ?? 'simulated'),
                'type' => $resolvedType,
                'confidence' => isset($result['confidence'])
                    ? (float) $result['confidence']
                    : null,
                'metadata' => [
                    'provider_metadata' => is_array($result['metadata'] ?? null)
                        ? $result['metadata']
                        : [],
                ],
            ],
        );

        $document = $transitionService->transition(
            document: $document,
            toStatus: 'ready_for_review',
            consumerName: 'classification-transition',
            messageId: (string) Str::uuid(),
            metadata: [
                'pipeline' => 'classification',
                'classification_type' => $resolvedType,
            ],
        );

        $processingEventRecorder->record(
            document: $document,
            messageId: $messageId,
            consumerName: 'classification',
            event: 'document.classified',
            statusFrom: $statusBeforeProcessing,
            statusTo: (string) $document->status,
            traceId: $traceId,
            metadata: array_merge($metadata, [
                'classification_type' => $resolvedType,
            ]),
        );
    }

    public function failed(Throwable $exception): void
    {
        DeadLetterConsumerJob::dispatch(
            payload: $this->deadLetterPayload($exception),
            terminalStatus: 'classification_failed',
        )
            ->onConnection($this->resolveQueueConnection())
            ->onQueue('queue.dead-letters');
    }

    protected function isAlreadyProcessed(Document $document, string $messageId): bool
    {
        return ProcessingEvent::query()
            ->where('tenant_id', $document->tenant_id)
            ->where('document_id', $document->id)
            ->where('message_id', $messageId)
            ->where('consumer_name', 'classification')
            ->exists();
    }

    protected function redispatchWhileExtractionPending(): void
    {
        $waitAttempt = $this->resolveClassificationWaitAttempt();
        $retryAttempts = $this->resolveRetryAttempts();

        if ($waitAttempt >= $retryAttempts) {
            throw new RuntimeException('OCR extraction did not finish before classification retries were exhausted.');
        }

        $payload = $this->payload;
        $metadata = $this->resolveMetadata();
        $metadata['classification_wait_attempt'] = $waitAttempt + 1;
        $payload['metadata'] = $metadata;
        $payload['retry_count'] = ((int) ($payload['retry_count'] ?? 0)) + 1;

        self::dispatch($payload)
            ->delay(now()->addSeconds($this->resolveScanWaitDelaySeconds()))
            ->onConnection($this->resolveQueueConnection())
            ->onQueue('queue.classify.general');
    }

    protected function resolveRetryAttempts(): int
    {
        $configuredAttempts = (int) config('processing.retry_attempts', 3);

        return $configuredAttempts > 0 ? $configuredAttempts : 3;
    }

    protected function resolveQueueConnection(): string
    {
        $configuredConnection = config('processing.queue_connection', config('queue.default', 'sync'));

        return is_string($configuredConnection) && $configuredConnection !== ''
            ? $configuredConnection
            : 'sync';
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

    protected function resolveClassificationWaitAttempt(): int
    {
        $metadata = $this->resolveMetadata();

        return max(0, (int) ($metadata['classification_wait_attempt'] ?? 0));
    }

    protected function resolveProviderDegradedAttempt(): int
    {
        $metadata = $this->resolveMetadata();

        return max(0, (int) ($metadata['provider_degraded_attempt'] ?? 0));
    }

    protected function resolveProviderDegradedRequeueDelaySeconds(): int
    {
        $delay = (int) config('processing.provider_degraded_requeue_delay_seconds', 30);

        return $delay > 0 ? $delay : 30;
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
            consumerName: 'classification-tenant-mismatch',
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
        $metadata['failed_consumer'] = 'classification';
        $metadata['failure_reason'] = $exception->getMessage();
        $payload['metadata'] = $metadata;

        return $payload;
    }

    protected function redispatchWhileProviderDegraded(ProviderDegradedException $exception): void
    {
        $degradedAttempt = $this->resolveProviderDegradedAttempt();
        $retryAttempts = $this->resolveRetryAttempts();

        if ($degradedAttempt >= $retryAttempts) {
            throw new RuntimeException(
                sprintf(
                    'Classification provider [%s] remained degraded after %d attempts (%s).',
                    $exception->provider,
                    $degradedAttempt,
                    $exception->reason,
                ),
                previous: $exception,
            );
        }

        $payload = $this->payload;
        $metadata = $this->resolveMetadata();
        $metadata['provider_degraded_attempt'] = $degradedAttempt + 1;
        $metadata['provider_degraded_reason'] = $exception->reason;
        $metadata['provider_degraded_provider'] = $exception->provider;
        $payload['metadata'] = $metadata;
        $payload['retry_count'] = ((int) ($payload['retry_count'] ?? 0)) + 1;

        $queue = is_string($this->queue) && $this->queue !== ''
            ? $this->queue
            : 'queue.classify.general';

        self::dispatch($payload)
            ->delay(now()->addSeconds($this->resolveProviderDegradedRequeueDelaySeconds()))
            ->onConnection($this->resolveQueueConnection())
            ->onQueue($queue);
    }
}
