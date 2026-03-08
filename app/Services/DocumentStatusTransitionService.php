<?php

namespace App\Services;

use App\Events\DocumentProcessingEvent;
use App\Events\DocumentStatusUpdated;
use App\Models\Document;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class DocumentStatusTransitionService
{
    /**
     * @var array<string, list<string>>
     */
    protected const ALLOWED_TRANSITIONS = [
        'uploaded' => ['scanning'],
        'scanning' => ['scan_passed', 'scan_failed'],
        'scan_passed' => ['extracting'],
        'extracting' => ['classifying', 'extraction_failed'],
        'classifying' => ['ready_for_review', 'classification_failed'],
        'ready_for_review' => ['reviewed', 'approved'],
        'reviewed' => ['approved'],
        'scan_failed' => [],
        'extraction_failed' => [],
        'classification_failed' => [],
        'approved' => [],
    ];

    public function __construct(
        public ProcessingEventRecorder $processingEventRecorder,
    ) {}

    /**
     * @param  array<string, mixed>  $metadata
     */
    public function transition(
        Document $document,
        string $toStatus,
        string $consumerName = 'pipeline-transition',
        ?string $messageId = null,
        array $metadata = [],
    ): Document {
        /**
         * @var array{
         *     document: Document,
         *     message_id: string,
         *     trace_id: string,
         *     metadata: array<string, mixed>
         * } $transitionResult
         */
        $transitionResult = DB::transaction(function () use ($document, $toStatus, $consumerName, $messageId, $metadata): array {
            /** @var Document $lockedDocument */
            $lockedDocument = Document::query()
                ->whereKey($document->id)
                ->lockForUpdate()
                ->firstOrFail();

            $fromStatus = (string) $lockedDocument->status;

            if (! $this->canTransition($fromStatus, $toStatus)) {
                throw new InvalidArgumentException(
                    sprintf('Invalid document status transition from [%s] to [%s].', $fromStatus, $toStatus),
                );
            }

            $traceId = $this->ensureProcessingTraceId($lockedDocument);
            $resolvedMessageId = $messageId ?? (string) Str::uuid();
            $eventMetadata = array_merge($metadata, [
                'from_status' => $fromStatus,
                'to_status' => $toStatus,
            ]);

            $lockedDocument->update([
                'status' => $toStatus,
                'processing_trace_id' => $traceId,
            ]);

            $this->processingEventRecorder->record(
                $lockedDocument,
                $resolvedMessageId,
                $consumerName,
                'document.status.transitioned',
                $fromStatus,
                $toStatus,
                $traceId,
                $eventMetadata,
            );

            return [
                'document' => $lockedDocument->fresh(),
                'message_id' => $resolvedMessageId,
                'trace_id' => $traceId,
                'metadata' => $eventMetadata,
            ];
        });

        event(new DocumentProcessingEvent(
            messageId: $transitionResult['message_id'],
            traceId: $transitionResult['trace_id'],
            tenantId: $transitionResult['document']->tenant_id,
            documentId: $transitionResult['document']->id,
            event: 'document.status.transitioned',
            timestamp: now()->toImmutable(),
            metadata: $transitionResult['metadata'],
            retryCount: 0,
        ));

        $transitionedDocument = $transitionResult['document']->load('classification');

        event(new DocumentStatusUpdated(
            documentId: $transitionedDocument->id,
            tenantId: $transitionedDocument->tenant_id,
            statusFrom: is_string($transitionResult['metadata']['from_status'] ?? null)
                ? $transitionResult['metadata']['from_status']
                : null,
            statusTo: is_string($transitionResult['metadata']['to_status'] ?? null)
                ? $transitionResult['metadata']['to_status']
                : $toStatus,
            event: 'document.status.transitioned',
            traceId: $transitionResult['trace_id'],
            occurredAt: now()->toImmutable(),
            classification: $this->formatClassificationSnapshot($transitionedDocument),
        ));

        return $transitionedDocument;
    }

    public function canTransition(string $fromStatus, string $toStatus): bool
    {
        $allowedStatuses = self::ALLOWED_TRANSITIONS[$fromStatus] ?? [];

        return in_array($toStatus, $allowedStatuses, true);
    }

    protected function ensureProcessingTraceId(Document $document): string
    {
        if (is_string($document->processing_trace_id) && $document->processing_trace_id !== '') {
            return $document->processing_trace_id;
        }

        return (string) Str::uuid();
    }

    /**
     * @return array{provider: string, type: string, confidence: float|string|null}|null
     */
    protected function formatClassificationSnapshot(Document $document): ?array
    {
        $classification = $document->classification;

        if ($classification === null) {
            return null;
        }

        return [
            'provider' => $classification->provider,
            'type' => $classification->type,
            'confidence' => $classification->confidence,
        ];
    }
}
