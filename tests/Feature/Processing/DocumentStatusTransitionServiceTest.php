<?php

use App\Events\DocumentProcessingEvent;
use App\Models\Client;
use App\Models\Document;
use App\Models\Matter;
use App\Models\ProcessingEvent;
use App\Models\Tenant;
use App\Services\DocumentStatusTransitionService;
use Illuminate\Support\Facades\Event;

afterEach(function () {
    tenancy()->end();
});

function createProcessingDocument(string $status = 'uploaded'): Document
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
        'processing_trace_id' => null,
    ]);
}

test('valid transition advances status and records transition event', function () {
    Event::fake([DocumentProcessingEvent::class]);
    $document = createProcessingDocument();

    $updatedDocument = app(DocumentStatusTransitionService::class)->transition(
        document: $document,
        toStatus: 'scanning',
        consumerName: 'virus-scan',
        messageId: 'message-001',
        metadata: ['source' => 'test-suite'],
    );

    $processingEvent = ProcessingEvent::query()->firstWhere('document_id', $document->id);

    expect($updatedDocument->status)->toBe('scanning')
        ->and($updatedDocument->processing_trace_id)->not()->toBeNull()
        ->and($processingEvent)->not()->toBeNull()
        ->and($processingEvent->status_from)->toBe('uploaded')
        ->and($processingEvent->status_to)->toBe('scanning')
        ->and($processingEvent->message_id)->toBe('message-001');
});

test('invalid transition is rejected and leaves status unchanged', function () {
    Event::fake([DocumentProcessingEvent::class]);
    $document = createProcessingDocument('uploaded');

    expect(fn () => app(DocumentStatusTransitionService::class)->transition(
        document: $document,
        toStatus: 'approved',
    ))->toThrow(\InvalidArgumentException::class);

    expect($document->fresh()->status)->toBe('uploaded')
        ->and(ProcessingEvent::query()->count())->toBe(0);
});

test('terminal states reject further transitions', function () {
    Event::fake([DocumentProcessingEvent::class]);
    $document = createProcessingDocument('approved');

    expect(fn () => app(DocumentStatusTransitionService::class)->transition(
        document: $document,
        toStatus: 'reviewed',
    ))->toThrow(\InvalidArgumentException::class);

    expect($document->fresh()->status)->toBe('approved')
        ->and(ProcessingEvent::query()->count())->toBe(0);
});
