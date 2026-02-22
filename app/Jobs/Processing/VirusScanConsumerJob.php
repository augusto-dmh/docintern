<?php

namespace App\Jobs\Processing;

use App\Models\Document;
use App\Models\ProcessingEvent;
use App\Services\DocumentStatusTransitionService;
use App\Services\ProcessingEventRecorder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

class VirusScanConsumerJob implements ShouldQueue
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

        if ($statusBeforeProcessing === 'uploaded') {
            $document = $transitionService->transition(
                document: $document,
                toStatus: 'scanning',
                consumerName: 'virus-scan-transition',
                messageId: (string) Str::uuid(),
                metadata: ['pipeline' => 'virus-scan'],
            );
        }

        $document = $document->fresh();

        if ($document === null || ! in_array($document->status, ['scanning', 'scan_passed'], true)) {
            return;
        }

        $simulateScanFailure = ($metadata['simulate_scan_failure'] ?? false) === true;

        if ($simulateScanFailure && $document->status === 'scanning') {
            $document = $transitionService->transition(
                document: $document,
                toStatus: 'scan_failed',
                consumerName: 'virus-scan-transition',
                messageId: (string) Str::uuid(),
                metadata: ['pipeline' => 'virus-scan', 'simulate_scan_failure' => true],
            );

            $processingEventRecorder->record(
                document: $document,
                messageId: $messageId,
                consumerName: 'virus-scan',
                event: 'document.virus_scan.failed',
                statusFrom: $statusBeforeProcessing,
                statusTo: 'scan_failed',
                traceId: $traceId,
                metadata: array_merge($metadata, ['simulate_scan_failure' => true]),
            );

            return;
        }

        if ($document->status === 'scanning') {
            $document = $transitionService->transition(
                document: $document,
                toStatus: 'scan_passed',
                consumerName: 'virus-scan-transition',
                messageId: (string) Str::uuid(),
                metadata: ['pipeline' => 'virus-scan'],
            );
        }

        $processingEventRecorder->record(
            document: $document,
            messageId: $messageId,
            consumerName: 'virus-scan',
            event: 'document.virus_scan.passed',
            statusFrom: $statusBeforeProcessing,
            statusTo: (string) $document->status,
            traceId: $traceId,
            metadata: $metadata,
        );
    }

    public function failed(Throwable $exception): void
    {
        DeadLetterConsumerJob::dispatch(
            payload: $this->deadLetterPayload($exception),
            terminalStatus: 'scan_failed',
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
            ->where('consumer_name', 'virus-scan')
            ->exists();
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
            consumerName: 'virus-scan-tenant-mismatch',
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
        $metadata['failed_consumer'] = 'virus-scan';
        $metadata['failure_reason'] = $exception->getMessage();
        $payload['metadata'] = $metadata;

        return $payload;
    }
}
