<?php

namespace App\Services;

use App\Events\DocumentProcessingEvent;
use App\Events\DocumentStatusUpdated;
use App\Models\Document;
use App\Models\Matter;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class DocumentUploadService
{
    public function __construct(
        public ProcessingEventRecorder $processingEventRecorder,
    ) {}

    public function upload(UploadedFile $file, Matter $matter, User $user, string $title): Document
    {
        /**
         * @var array{
         *     document: Document,
         *     message_id: string,
         *     trace_id: string,
         *     metadata: array{
         *         original_filename: string,
         *         mime_type: string|null,
         *         size_bytes: int,
         *         uploaded_by_user_id: int
         *     }
         * } $uploadResult
         */
        $uploadResult = DB::transaction(function () use ($file, $matter, $user, $title): array {
            $messageId = (string) Str::uuid();
            $traceId = (string) Str::uuid();

            $document = Document::query()->create([
                'tenant_id' => $matter->tenant_id,
                'matter_id' => $matter->id,
                'uploaded_by' => $user->id,
                'title' => $title,
                'file_path' => 'pending',
                'file_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getClientMimeType(),
                'file_size' => $file->getSize() ?? 0,
                'status' => 'uploaded',
                'processing_trace_id' => $traceId,
            ]);

            $filePath = $this->buildFilePath($matter->tenant_id, $document->id, $file);

            $storedPath = Storage::disk('s3')->putFileAs(
                dirname($filePath),
                $file,
                basename($filePath),
            );

            if ($storedPath === false) {
                throw new RuntimeException('Failed to store document on S3.');
            }

            $document->update([
                'file_path' => $storedPath,
            ]);

            $document->auditLogs()->create([
                'tenant_id' => $matter->tenant_id,
                'user_id' => $user->id,
                'action' => 'uploaded',
                'metadata' => null,
            ]);

            $metadata = [
                'original_filename' => $document->file_name,
                'mime_type' => $document->mime_type,
                'size_bytes' => $document->file_size,
                'uploaded_by_user_id' => $user->id,
            ];

            $this->processingEventRecorder->record(
                $document,
                $messageId,
                'upload-dispatch',
                'document.uploaded',
                null,
                'uploaded',
                $traceId,
                $metadata,
            );

            return [
                'document' => $document->fresh(),
                'message_id' => $messageId,
                'trace_id' => $traceId,
                'metadata' => $metadata,
            ];
        });

        event(new DocumentProcessingEvent(
            messageId: $uploadResult['message_id'],
            traceId: $uploadResult['trace_id'],
            tenantId: $matter->tenant_id,
            documentId: $uploadResult['document']->id,
            event: 'document.uploaded',
            timestamp: now()->toImmutable(),
            metadata: $uploadResult['metadata'],
            retryCount: 0,
        ));

        event(new DocumentStatusUpdated(
            documentId: $uploadResult['document']->id,
            tenantId: $matter->tenant_id,
            statusFrom: null,
            statusTo: 'uploaded',
            event: 'document.uploaded',
            traceId: $uploadResult['trace_id'],
            occurredAt: now()->toImmutable(),
        ));

        return $uploadResult['document'];
    }

    public function generatePresignedUrl(Document $document, int $ttlMinutes = 15): string
    {
        return Storage::disk('s3')->temporaryUrl(
            $document->file_path,
            now()->addMinutes($ttlMinutes),
        );
    }

    public function delete(Document $document, User $user): void
    {
        if (Storage::disk('s3')->exists($document->file_path)) {
            Storage::disk('s3')->delete($document->file_path);
        }

        $document->auditLogs()->create([
            'tenant_id' => $document->tenant_id,
            'user_id' => $user->id,
            'action' => 'deleted',
            'metadata' => null,
        ]);
    }

    protected function buildFilePath(string $tenantId, int $documentId, UploadedFile $file): string
    {
        $name = pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME);
        $extension = $file->getClientOriginalExtension();
        $slug = Str::slug($name);

        if ($slug === '') {
            $slug = 'document';
        }

        return "tenants/{$tenantId}/documents/{$documentId}/{$slug}.{$extension}";
    }
}
