<?php

namespace App\Services;

use App\Models\Document;
use App\Models\ProcessingEvent;
use Illuminate\Support\Str;

class ProcessingEventRecorder
{
    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function record(
        Document $document,
        string $messageId,
        string $consumerName,
        string $event,
        ?string $statusFrom = null,
        ?string $statusTo = null,
        ?string $traceId = null,
        ?array $metadata = null,
    ): ProcessingEvent
    {
        return ProcessingEvent::query()->firstOrCreate(
            [
                'tenant_id' => $document->tenant_id,
                'message_id' => $messageId,
                'consumer_name' => $consumerName,
            ],
            [
                'document_id' => $document->id,
                'trace_id' => $this->resolveTraceId($document, $traceId),
                'event' => $event,
                'status_from' => $statusFrom,
                'status_to' => $statusTo,
                'metadata' => $metadata,
            ],
        );
    }

    protected function resolveTraceId(Document $document, ?string $traceId): string
    {
        $resolvedTraceId = $traceId ?? $document->processing_trace_id;

        if (is_string($resolvedTraceId) && $resolvedTraceId !== '') {
            return $resolvedTraceId;
        }

        return (string) Str::uuid();
    }
}
