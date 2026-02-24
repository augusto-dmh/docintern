<?php

use App\Models\Client;
use App\Models\Document;
use App\Models\ExtractedData;
use App\Models\Matter;
use App\Models\Tenant;
use App\Services\Processing\LiveComprehendClassificationProvider;
use App\Services\Processing\LiveTextractOcrProvider;
use Aws\Laravel\AwsFacade as Aws;
use Aws\Result;

afterEach(function (): void {
    tenancy()->end();
    \Mockery::close();
});

test('live textract provider extracts line text and class hint from s3 document', function (): void {
    $document = createLiveProviderDocument('nda-contract.pdf');

    $textractClient = \Mockery::mock();
    $textractClient->shouldReceive('detectDocumentText')
        ->once()
        ->andReturn(new Result([
            'Blocks' => [
                ['BlockType' => 'PAGE', 'Id' => 'p1'],
                ['BlockType' => 'LINE', 'Text' => 'Master Service Agreement'],
                ['BlockType' => 'LINE', 'Text' => 'Effective date: 2026-02-24'],
            ],
            'DetectDocumentTextModelVersion' => '1.0',
        ]));

    Aws::shouldReceive('createClient')
        ->once()
        ->with('textract')
        ->andReturn($textractClient);

    /** @var LiveTextractOcrProvider $provider */
    $provider = app(LiveTextractOcrProvider::class);

    $result = $provider->extract($document, ['metadata' => []]);

    expect($result['provider'])->toBe('textract')
        ->and($result['classification_hint'])->toBe('contract')
        ->and($result['extracted_text'])->toContain('Master Service Agreement')
        ->and($result['payload']['block_count'])->toBe(3)
        ->and($result['payload']['lines'])->toHaveCount(2)
        ->and($result['metadata']['source'])->toBe('textract.detect_document_text');
});

test('live comprehend provider classifies extracted text with endpoint and confidence', function (): void {
    config()->set('processing.comprehend.endpoint_arn', 'arn:aws:comprehend:us-east-1:123456789012:document-classifier-endpoint/docintern');

    $document = createLiveProviderDocument('client-contract.pdf');
    $extractedData = ExtractedData::query()->create([
        'tenant_id' => $document->tenant_id,
        'document_id' => $document->id,
        'provider' => 'textract',
        'extracted_text' => 'This contract sets terms between parties.',
        'payload' => [],
        'metadata' => [],
    ]);

    $comprehendClient = \Mockery::mock();
    $comprehendClient->shouldReceive('classifyDocument')
        ->once()
        ->andReturn(new Result([
            'Classes' => [
                ['Name' => 'contract', 'Score' => 0.98],
                ['Name' => 'invoice', 'Score' => 0.04],
            ],
        ]));

    Aws::shouldReceive('createClient')
        ->once()
        ->with('comprehend')
        ->andReturn($comprehendClient);

    /** @var LiveComprehendClassificationProvider $provider */
    $provider = app(LiveComprehendClassificationProvider::class);

    $result = $provider->classify($document, $extractedData, ['metadata' => []]);

    expect($result['provider'])->toBe('comprehend')
        ->and($result['type'])->toBe('contract')
        ->and($result['confidence'])->toBe(0.98)
        ->and($result['metadata']['selected_label'])->toBe('contract')
        ->and($result['metadata']['endpoint_arn'])->toContain('document-classifier-endpoint');
});

test('live comprehend provider falls back to routing heuristics for unknown labels', function (): void {
    config()->set('processing.comprehend.endpoint_arn', 'arn:aws:comprehend:us-east-1:123456789012:document-classifier-endpoint/docintern');

    $document = createLiveProviderDocument('invoice-march.pdf');
    $extractedData = ExtractedData::query()->create([
        'tenant_id' => $document->tenant_id,
        'document_id' => $document->id,
        'provider' => 'textract',
        'extracted_text' => 'Invoice total due and payment receipt details.',
        'payload' => [],
        'metadata' => [],
    ]);

    $comprehendClient = \Mockery::mock();
    $comprehendClient->shouldReceive('classifyDocument')
        ->once()
        ->andReturn(new Result([
            'Classes' => [
                ['Name' => 'financial_doc', 'Score' => 0.91],
            ],
        ]));

    Aws::shouldReceive('createClient')
        ->once()
        ->with('comprehend')
        ->andReturn($comprehendClient);

    /** @var LiveComprehendClassificationProvider $provider */
    $provider = app(LiveComprehendClassificationProvider::class);

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
