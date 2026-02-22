<?php

namespace App\Jobs\Processing;

use App\Models\Document;
use App\Models\ProcessingEvent;
use App\Services\ProcessingEventRecorder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;

class AuditLogConsumerJob implements ShouldQueue
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

    public function handle(ProcessingEventRecorder $processingEventRecorder): void
    {
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

        $document->auditLogs()->create([
            'tenant_id' => $document->tenant_id,
            'user_id' => null,
            'action' => 'processing_ingested',
            'metadata' => [
                'trace_id' => $traceId,
                'message_id' => $messageId,
                'pipeline' => 'audit-log',
            ],
        ]);

        $processingEventRecorder->record(
            document: $document,
            messageId: $messageId,
            consumerName: 'audit-log',
            event: 'document.audit.logged',
            statusFrom: $document->status,
            statusTo: $document->status,
            traceId: $traceId,
            metadata: $metadata,
        );
    }

    protected function isAlreadyProcessed(Document $document, string $messageId): bool
    {
        return ProcessingEvent::query()
            ->where('tenant_id', $document->tenant_id)
            ->where('document_id', $document->id)
            ->where('message_id', $messageId)
            ->where('consumer_name', 'audit-log')
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
            consumerName: 'audit-log-tenant-mismatch',
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
}
