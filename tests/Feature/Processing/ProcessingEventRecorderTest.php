<?php

use App\Models\Client;
use App\Models\Document;
use App\Models\Matter;
use App\Models\ProcessingEvent;
use App\Models\Tenant;
use App\Services\ProcessingEventRecorder;

afterEach(function () {
    tenancy()->end();
});

test('processing event recorder is idempotent for duplicate message and consumer keys', function () {
    $tenant = Tenant::factory()->create();
    $client = Client::factory()->create(['tenant_id' => $tenant->id]);
    $matter = Matter::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
    ]);
    $document = Document::factory()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
        'status' => 'uploaded',
        'processing_trace_id' => (string) fake()->uuid(),
    ]);

    $recorder = app(ProcessingEventRecorder::class);

    $firstRecord = $recorder->record(
        document: $document,
        messageId: 'message-xyz',
        consumerName: 'ocr-extraction',
        event: 'document.status.transitioned',
        statusFrom: 'uploaded',
        statusTo: 'scanning',
        traceId: $document->processing_trace_id,
        metadata: ['attempt' => 1],
    );

    $secondRecord = $recorder->record(
        document: $document,
        messageId: 'message-xyz',
        consumerName: 'ocr-extraction',
        event: 'document.status.transitioned',
        statusFrom: 'scanning',
        statusTo: 'scan_passed',
        traceId: $document->processing_trace_id,
        metadata: ['attempt' => 2],
    );

    $storedRecord = ProcessingEvent::query()->sole();

    expect($firstRecord->id)->toBe($secondRecord->id)
        ->and(ProcessingEvent::query()->count())->toBe(1)
        ->and($storedRecord->status_from)->toBe('uploaded')
        ->and($storedRecord->status_to)->toBe('scanning')
        ->and($storedRecord->metadata)->toBe(['attempt' => 1]);
});
