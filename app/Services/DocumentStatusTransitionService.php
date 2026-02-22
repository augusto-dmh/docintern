<?php

namespace App\Services;

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
    ): Document
    {
        /** @var Document $updatedDocument */
        $updatedDocument = DB::transaction(function () use ($document, $toStatus, $consumerName, $messageId, $metadata): Document {
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
                array_merge($metadata, [
                    'from_status' => $fromStatus,
                    'to_status' => $toStatus,
                ]),
            );

            return $lockedDocument->fresh();
        });

        return $updatedDocument;
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
}
