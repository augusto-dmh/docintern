<?php

use App\Jobs\Processing\ClassificationConsumerJob;
use App\Jobs\Processing\OcrExtractionConsumerJob;
use App\Jobs\Processing\VirusScanConsumerJob;
use App\Models\Client;
use App\Models\Document;
use App\Models\DocumentClassification;
use App\Models\ExtractedData;
use App\Models\Matter;
use App\Models\ProcessingEvent;
use App\Models\Tenant;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

afterEach(function () {
    tenancy()->end();
});

function createPipelineDocument(string $status = 'uploaded', ?string $fileName = null): Document
{
    $tenant = Tenant::factory()->create();
    $client = Client::factory()->create(['tenant_id' => $tenant->id]);
    $matter = Matter::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
    ]);

    return Document::factory()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
        'status' => $status,
        'file_name' => $fileName ?? 'contract.pdf',
        'processing_trace_id' => (string) Str::uuid(),
    ]);
}

/**
 * @param  array<string, mixed>  $metadata
 * @return array<string, mixed>
 */
function pipelinePayload(Document $document, array $metadata = []): array
{
    return [
        'message_id' => (string) Str::uuid(),
        'trace_id' => (string) Str::uuid(),
        'tenant_id' => $document->tenant_id,
        'document_id' => $document->id,
        'event' => 'document.uploaded',
        'timestamp' => now()->toISOString(),
        'metadata' => $metadata,
        'retry_count' => 0,
    ];
}

test('pipeline consumers process a document end to end to ready for review', function () {
    Queue::fake();

    $document = createPipelineDocument('uploaded', 'msa-contract.pdf');
    $payload = pipelinePayload($document, ['classification_hint' => 'contract']);

    app()->call([new VirusScanConsumerJob($payload), 'handle']);
    expect($document->fresh()->status)->toBe('scan_passed');

    app()->call([new OcrExtractionConsumerJob($payload), 'handle']);

    $document->refresh();

    expect($document->status)->toBe('classifying')
        ->and(ExtractedData::query()->where('document_id', $document->id)->exists())->toBeTrue();

    $classificationJob = Queue::pushed(ClassificationConsumerJob::class)->first();

    expect($classificationJob)->toBeInstanceOf(ClassificationConsumerJob::class);

    app()->call([$classificationJob, 'handle']);

    $document->refresh();
    $classification = DocumentClassification::query()->firstWhere('document_id', $document->id);

    expect($document->status)->toBe('ready_for_review')
        ->and($classification)->not()->toBeNull()
        ->and($classification->type)->toBe('contract');

    expect(ProcessingEvent::query()->where('consumer_name', 'virus-scan')->count())->toBe(1)
        ->and(ProcessingEvent::query()->where('consumer_name', 'ocr-extraction')->count())->toBe(1)
        ->and(ProcessingEvent::query()->where('consumer_name', 'classification')->count())->toBe(1);
});

test('pipeline consumers remain idempotent when duplicate messages are redelivered', function () {
    Queue::fake();

    $document = createPipelineDocument('uploaded', 'invoice.pdf');
    $payload = pipelinePayload($document, ['classification_hint' => 'invoice']);

    $virusScanJob = new VirusScanConsumerJob($payload);
    $ocrExtractionJob = new OcrExtractionConsumerJob($payload);

    app()->call([$virusScanJob, 'handle']);
    app()->call([$virusScanJob, 'handle']);

    app()->call([$ocrExtractionJob, 'handle']);
    app()->call([$ocrExtractionJob, 'handle']);

    $classificationJob = Queue::pushed(ClassificationConsumerJob::class)->first();

    expect($classificationJob)->toBeInstanceOf(ClassificationConsumerJob::class);

    app()->call([$classificationJob, 'handle']);
    app()->call([$classificationJob, 'handle']);

    expect(ProcessingEvent::query()->where('consumer_name', 'virus-scan')->count())->toBe(1)
        ->and(ProcessingEvent::query()->where('consumer_name', 'ocr-extraction')->count())->toBe(1)
        ->and(ProcessingEvent::query()->where('consumer_name', 'classification')->count())->toBe(1)
        ->and(ExtractedData::query()->where('document_id', $document->id)->count())->toBe(1)
        ->and(DocumentClassification::query()->where('document_id', $document->id)->count())->toBe(1)
        ->and($document->fresh()->status)->toBe('ready_for_review');
});

test('tenant mismatched payload does not mutate cross tenant document state', function () {
    $allowedDocument = createPipelineDocument('uploaded', 'tax-form.pdf');
    $otherTenantDocument = createPipelineDocument('uploaded', 'nda.pdf');

    $payload = pipelinePayload($otherTenantDocument);
    $payload['tenant_id'] = $allowedDocument->tenant_id;

    app()->call([new VirusScanConsumerJob($payload), 'handle']);
    app()->call([new OcrExtractionConsumerJob($payload), 'handle']);

    expect($otherTenantDocument->fresh()->status)->toBe('uploaded')
        ->and($allowedDocument->fresh()->status)->toBe('uploaded')
        ->and(ExtractedData::query()->where('document_id', $otherTenantDocument->id)->exists())->toBeFalse();

    $tenantMismatchEvents = ProcessingEvent::query()
        ->where('document_id', $otherTenantDocument->id)
        ->whereIn('consumer_name', ['virus-scan-tenant-mismatch', 'ocr-extraction-tenant-mismatch'])
        ->count();

    expect($tenantMismatchEvents)->toBe(2);
});
