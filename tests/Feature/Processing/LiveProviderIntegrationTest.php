<?php

use App\Models\Client;
use App\Models\Document;
use App\Models\ExtractedData;
use App\Models\Matter;
use App\Models\Tenant;
use App\Services\Processing\LiveOpenAiClassificationProvider;
use App\Services\Processing\OpenAiOcrProvider;
use App\Services\Processing\ProviderDegradedException;
use Illuminate\Support\Facades\Http;

afterEach(function (): void {
    tenancy()->end();
    \Mockery::close();
});

test('openai ocr provider extracts text and class hint', function (): void {
    config()->set('processing.openai.api_key', 'test-openai-key');
    config()->set('processing.openai.model', 'gpt-4o-mini');
    config()->set('processing.openai.ocr_model', 'gpt-4o-mini');
    config()->set('processing.openai.base_url', 'https://api.openai.com/v1');
    config()->set('processing.openai.timeout_seconds', 15);

    $document = createLiveProviderDocument('nda-contract.pdf');

    Http::fake([
        'https://api.openai.com/v1/chat/completions' => Http::response([
            'id' => 'chatcmpl-ocr-1',
            'choices' => [
                [
                    'message' => [
                        'content' => '{"extracted_text":"Master Service Agreement\nEffective date: 2026-02-24","lines":["Master Service Agreement","Effective date: 2026-02-24"],"classification_hint":"contract"}',
                    ],
                ],
            ],
        ], 200),
    ]);

    /** @var OpenAiOcrProvider $provider */
    $provider = app(OpenAiOcrProvider::class);

    $result = $provider->extract($document, [
        'metadata' => [
            'ocr_source_text' => 'Master Service Agreement Effective date: 2026-02-24',
        ],
    ]);

    expect($result['provider'])->toBe('openai')
        ->and($result['classification_hint'])->toBe('contract')
        ->and($result['extracted_text'])->toContain('Master Service Agreement')
        ->and($result['payload']['line_count'])->toBe(2)
        ->and($result['payload']['lines'])->toHaveCount(2)
        ->and($result['metadata']['source'])->toBe('openai.chat.completions');
});

test('openai ocr provider marks rate limiting as degraded', function (): void {
    config()->set('processing.openai.api_key', 'test-openai-key');
    config()->set('processing.openai.base_url', 'https://api.openai.com/v1');
    config()->set('processing.openai.timeout_seconds', 15);

    $document = createLiveProviderDocument('degraded-ocr.pdf');

    Http::fake([
        'https://api.openai.com/v1/chat/completions' => Http::response([
            'error' => ['message' => 'rate limited'],
        ], 429),
    ]);

    /** @var OpenAiOcrProvider $provider */
    $provider = app(OpenAiOcrProvider::class);

    expect(fn () => $provider->extract($document, [
        'metadata' => [
            'ocr_source_text' => 'This text triggers a transient OpenAI response.',
        ],
    ]))->toThrow(ProviderDegradedException::class);
});

test('live openai provider classifies extracted text with confidence', function (): void {
    config()->set('processing.openai.api_key', 'test-openai-key');
    config()->set('processing.openai.model', 'gpt-4o-mini');
    config()->set('processing.openai.base_url', 'https://api.openai.com/v1');
    config()->set('processing.openai.timeout_seconds', 15);

    $document = createLiveProviderDocument('client-contract.pdf');
    $extractedData = ExtractedData::query()->create([
        'tenant_id' => $document->tenant_id,
        'document_id' => $document->id,
        'provider' => 'openai',
        'extracted_text' => 'This contract sets terms between parties.',
        'payload' => [],
        'metadata' => [],
    ]);

    Http::fake([
        'https://api.openai.com/v1/chat/completions' => Http::response([
            'id' => 'chatcmpl-test-1',
            'choices' => [
                [
                    'message' => [
                        'content' => '{"label":"contract","confidence":0.98}',
                    ],
                ],
            ],
        ], 200),
    ]);

    /** @var LiveOpenAiClassificationProvider $provider */
    $provider = app(LiveOpenAiClassificationProvider::class);

    $result = $provider->classify($document, $extractedData, ['metadata' => []]);

    expect($result['provider'])->toBe('openai')
        ->and($result['type'])->toBe('contract')
        ->and($result['confidence'])->toBe(0.98)
        ->and($result['metadata']['selected_label'])->toBe('contract')
        ->and($result['metadata']['model'])->toBe('gpt-4o-mini');
});

test('live openai provider falls back to routing heuristics for unknown labels', function (): void {
    config()->set('processing.openai.api_key', 'test-openai-key');
    config()->set('processing.openai.model', 'gpt-4o-mini');
    config()->set('processing.openai.base_url', 'https://api.openai.com/v1');
    config()->set('processing.openai.timeout_seconds', 15);

    $document = createLiveProviderDocument('invoice-march.pdf');
    $extractedData = ExtractedData::query()->create([
        'tenant_id' => $document->tenant_id,
        'document_id' => $document->id,
        'provider' => 'openai',
        'extracted_text' => 'Invoice total due and payment receipt details.',
        'payload' => [],
        'metadata' => [],
    ]);

    Http::fake([
        'https://api.openai.com/v1/chat/completions' => Http::response([
            'id' => 'chatcmpl-test-2',
            'choices' => [
                [
                    'message' => [
                        'content' => '{"label":"financial_doc","confidence":0.91}',
                    ],
                ],
            ],
        ], 200),
    ]);

    /** @var LiveOpenAiClassificationProvider $provider */
    $provider = app(LiveOpenAiClassificationProvider::class);

    $result = $provider->classify($document, $extractedData, ['metadata' => []]);

    expect($result['type'])->toBe('invoice')
        ->and($result['metadata']['selected_label'])->toBe('financial_doc');
});

function createLiveProviderDocument(string $fileName): Document
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
        'file_name' => $fileName,
        'file_path' => 'tenants/'.$tenant->id.'/documents/live-provider/'.$fileName,
        'status' => 'classifying',
    ]);
}
