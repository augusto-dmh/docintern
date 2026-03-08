<?php

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Document;
use App\Models\DocumentAnnotation;
use App\Models\DocumentClassification;
use App\Models\ExtractedData;
use App\Models\Matter;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DocumentUploadService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery\MockInterface;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
});

afterEach(function () {
    tenancy()->end();
});

function createDocumentCrudContext(): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create();
    $client = Client::factory()->create(['tenant_id' => $tenant->id]);
    $matter = Matter::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
    ]);

    setPermissionsTeamId($tenant->id);
    $user->assignRole('tenant-admin');

    return [$tenant, $user, $matter];
}

test('document index page can be rendered', function () {
    [$tenant, $user, $matter] = createDocumentCrudContext();
    $document = Document::factory()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
        'uploaded_by' => $user->id,
    ]);

    $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->get(route('documents.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('documents/Index')
            ->has('documents.data', 1)
            ->where('documents.data.0.id', $document->id)
            ->where('documents.data.0.matter.id', $matter->id)
            ->where('documents.data.0.uploader.id', $user->id)
        );
});

test('document create page can be rendered', function () {
    [$tenant, $user, $matter] = createDocumentCrudContext();

    $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->get(route('matters.documents.create', $matter))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('documents/Create')
            ->where('matter.id', $matter->id)
            ->where('matter.title', $matter->title)
        );
});

test('document can be stored', function () {
    [$tenant, $user, $matter] = createDocumentCrudContext();
    tenancy()->initialize($tenant);
    Storage::fake('s3');

    $response = $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->post(route('matters.documents.store', $matter), [
            'title' => 'Retainer Agreement',
            'file' => UploadedFile::fake()->create('retainer.pdf', 256, 'application/pdf'),
        ]);

    $document = Document::query()->firstWhere('title', 'Retainer Agreement');

    $response->assertRedirect(route('documents.show', $document));
    expect($document)->not()->toBeNull()
        ->and($document->tenant_id)->toBe($tenant->id)
        ->and($document->matter_id)->toBe($matter->id)
        ->and(str_starts_with($document->file_path, "tenants/{$tenant->id}/documents/{$document->id}/"))->toBeTrue();

    Storage::disk('s3')->assertExists($document->file_path);
    expect(AuditLog::query()
        ->where('auditable_type', Document::class)
        ->where('auditable_id', $document->id)
        ->where('action', 'uploaded')
        ->exists())->toBeTrue();
});

test('document store validates required fields', function () {
    [$tenant, $user, $matter] = createDocumentCrudContext();
    tenancy()->initialize($tenant);

    $response = $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->post(route('matters.documents.store', $matter), []);

    $response->assertSessionHasErrors(['title', 'file']);
});

test('document store validates file mime type and size', function () {
    [$tenant, $user, $matter] = createDocumentCrudContext();
    tenancy()->initialize($tenant);
    Storage::fake('s3');

    $invalidFileResponse = $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->post(route('matters.documents.store', $matter), [
            'title' => 'Invalid',
            'file' => UploadedFile::fake()->create('notes.txt', 20, 'text/plain'),
        ]);

    $invalidFileResponse->assertSessionHasErrors(['file']);

    $oversizedFileResponse = $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->post(route('matters.documents.store', $matter), [
            'title' => 'Too Large',
            'file' => UploadedFile::fake()->create('huge.pdf', 102401, 'application/pdf'),
        ]);

    $oversizedFileResponse->assertSessionHasErrors(['file']);
});

test('document show page can be rendered and logs a view event', function () {
    [$tenant, $user, $matter] = createDocumentCrudContext();
    $document = Document::factory()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
        'uploaded_by' => $user->id,
        'status' => 'classifying',
    ]);
    tenancy()->initialize($tenant);

    $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->get(route('documents.show', $document))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('documents/Show')
            ->where('document.id', $document->id)
            ->where('document.status', 'classifying')
            ->where('document.matter.id', $matter->id)
            ->where('document.uploader.id', $user->id)
            ->where('reviewWorkspace.preview.available', true)
            ->where('reviewWorkspace.preview.mode', 'pdf')
            ->where('reviewWorkspace.permissions.can_annotate', true)
            ->has('reviewWorkspace.annotations', 0)
        );

    expect(AuditLog::query()
        ->where('auditable_type', Document::class)
        ->where('auditable_id', $document->id)
        ->where('action', 'viewed')
        ->exists())->toBeTrue();
});

test('document show page includes classification payload when present', function () {
    [$tenant, $user, $matter] = createDocumentCrudContext();
    $document = Document::factory()->readyForReview()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
        'uploaded_by' => $user->id,
    ]);

    DocumentClassification::factory()->create([
        'tenant_id' => $tenant->id,
        'document_id' => $document->id,
        'provider' => 'openai',
        'type' => 'contract',
        'confidence' => 0.91,
    ]);
    ExtractedData::factory()->create([
        'tenant_id' => $tenant->id,
        'document_id' => $document->id,
        'provider' => 'openai',
        'extracted_text' => "Master Service Agreement\nEffective date: 2026-03-08",
        'payload' => [
            'lines' => [
                'Master Service Agreement',
                'Effective date: 2026-03-08',
            ],
            'key_values' => [
                ['label' => 'Agreement Type', 'value' => 'Master Service Agreement'],
            ],
        ],
        'metadata' => [
            'source' => 'openai.chat.completions',
        ],
    ]);
    DocumentAnnotation::factory()->create([
        'tenant_id' => $tenant->id,
        'document_id' => $document->id,
        'user_id' => $user->id,
        'type' => 'comment',
        'page_number' => 1,
        'content' => 'Double-check the renewal clause.',
    ]);

    tenancy()->initialize($tenant);

    $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->get(route('documents.show', $document))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('documents/Show')
            ->where('document.status', 'ready_for_review')
            ->where('document.classification.provider', 'openai')
            ->where('document.classification.type', 'contract')
            ->where('document.extracted_data.provider', 'openai')
            ->where('document.extracted_data.payload.key_values.0.label', 'Agreement Type')
            ->where('reviewWorkspace.preview.available', true)
            ->where('reviewWorkspace.preview.mode', 'pdf')
            ->where('reviewWorkspace.preview.mime_type', 'application/pdf')
            ->where('reviewWorkspace.permissions.can_annotate', true)
            ->where('reviewWorkspace.annotations.0.type', 'comment')
            ->where('reviewWorkspace.annotations.0.is_owner', true)
        );
});

test('document show page marks non pdf documents as unsupported preview mode', function () {
    [$tenant, $user, $matter] = createDocumentCrudContext();
    $document = Document::factory()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
        'uploaded_by' => $user->id,
        'file_name' => 'engagement-letter.docx',
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    ]);
    tenancy()->initialize($tenant);

    $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->get(route('documents.show', $document))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('documents/Show')
            ->where('reviewWorkspace.preview.available', false)
            ->where('reviewWorkspace.preview.mode', 'unsupported')
            ->where('reviewWorkspace.permissions.can_annotate', false)
            ->has('reviewWorkspace.annotations', 0)
            ->where(
                'reviewWorkspace.preview.mime_type',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            )
        );
});

test('document edit page can be rendered', function () {
    [$tenant, $user, $matter] = createDocumentCrudContext();
    $document = Document::factory()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
        'uploaded_by' => $user->id,
    ]);
    tenancy()->initialize($tenant);

    $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->get(route('documents.edit', $document))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('documents/Edit')
            ->where('document.id', $document->id)
            ->where('document.title', $document->title)
        );
});

test('document can be updated', function () {
    [$tenant, $user, $matter] = createDocumentCrudContext();
    $document = Document::factory()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
        'uploaded_by' => $user->id,
        'title' => 'Original Title',
    ]);
    tenancy()->initialize($tenant);

    $response = $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->put(route('documents.update', $document), [
            'title' => 'Updated Title',
        ]);

    $response->assertRedirect(route('documents.show', $document));
    expect($document->fresh()->title)->toBe('Updated Title');
});

test('document can be marked as reviewed from ready for review', function () {
    [$tenant, $user, $matter] = createDocumentCrudContext();
    $document = Document::factory()->readyForReview()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
        'uploaded_by' => $user->id,
    ]);
    tenancy()->initialize($tenant);

    $response = $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->post(route('documents.review', $document));

    $response->assertRedirect(route('documents.show', $document));
    expect($document->fresh()->status)->toBe('reviewed')
        ->and(AuditLog::query()
            ->where('auditable_type', Document::class)
            ->where('auditable_id', $document->id)
            ->where('action', 'reviewed')
            ->exists())->toBeTrue();
});

test('document can be approved from reviewed', function () {
    [$tenant, $user, $matter] = createDocumentCrudContext();
    $document = Document::factory()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
        'uploaded_by' => $user->id,
        'status' => 'reviewed',
    ]);
    tenancy()->initialize($tenant);

    $response = $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->post(route('documents.approve', $document));

    $response->assertRedirect(route('documents.show', $document));
    expect($document->fresh()->status)->toBe('approved')
        ->and(AuditLog::query()
            ->where('auditable_type', Document::class)
            ->where('auditable_id', $document->id)
            ->where('action', 'approved')
            ->exists())->toBeTrue();
});

test('document review transition validation fails for invalid status', function () {
    [$tenant, $user, $matter] = createDocumentCrudContext();
    $document = Document::factory()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
        'uploaded_by' => $user->id,
        'status' => 'uploaded',
    ]);
    tenancy()->initialize($tenant);

    $response = $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->post(route('documents.review', $document));

    $response->assertSessionHasErrors(['status']);
    expect($document->fresh()->status)->toBe('uploaded');
});

test('document can be destroyed and file is removed from s3', function () {
    [$tenant, $user, $matter] = createDocumentCrudContext();
    tenancy()->initialize($tenant);
    Storage::fake('s3');

    $document = Document::factory()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
        'uploaded_by' => $user->id,
        'file_path' => "tenants/{$tenant->id}/documents/999/witness-statement.pdf",
    ]);

    Storage::disk('s3')->put($document->file_path, 'document body');

    $response = $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->delete(route('documents.destroy', $document));

    $response->assertRedirect(route('documents.index'));
    expect(Document::query()->find($document->id))->toBeNull()
        ->and(Storage::disk('s3')->exists($document->file_path))->toBeFalse()
        ->and(AuditLog::query()
            ->where('auditable_type', Document::class)
            ->where('auditable_id', $document->id)
            ->where('action', 'deleted')
            ->exists())->toBeTrue();
});

test('document download redirects to a presigned url and logs event', function () {
    [$tenant, $user, $matter] = createDocumentCrudContext();
    $document = Document::factory()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
        'uploaded_by' => $user->id,
    ]);
    tenancy()->initialize($tenant);

    $downloadUrl = 'https://example.test/download/document';

    $this->mock(DocumentUploadService::class, function (MockInterface $mock) use ($document, $downloadUrl): void {
        $mock->shouldReceive('generatePresignedUrl')
            ->once()
            ->withArgs(function (Document $resolvedDocument) use ($document): bool {
                return $resolvedDocument->id === $document->id;
            })
            ->andReturn($downloadUrl);
    });

    $response = $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->get(route('documents.download', $document));

    $response->assertRedirect($downloadUrl);
    expect(AuditLog::query()
        ->where('auditable_type', Document::class)
        ->where('auditable_id', $document->id)
        ->where('action', 'downloaded')
        ->exists())->toBeTrue();
});

test('document preview streams pdf content for authorized tenant user', function () {
    [$tenant, $user, $matter] = createDocumentCrudContext();
    tenancy()->initialize($tenant);

    $document = Document::factory()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
        'uploaded_by' => $user->id,
        'file_name' => 'review-copy.pdf',
        'mime_type' => 'application/pdf',
        'file_path' => "tenants/{$tenant->id}/documents/{$matter->id}/review-copy.pdf",
    ]);

    $stream = fopen('php://temp', 'r+');
    fwrite($stream, 'pdf-preview-body');
    rewind($stream);

    $this->mock(DocumentUploadService::class, function (MockInterface $mock) use ($document, $stream): void {
        $mock->shouldReceive('supportsInlinePreview')
            ->once()
            ->withArgs(function (Document $resolvedDocument) use ($document): bool {
                return $resolvedDocument->id === $document->id;
            })
            ->andReturnTrue();
        $mock->shouldReceive('readStream')
            ->once()
            ->withArgs(function (Document $resolvedDocument) use ($document): bool {
                return $resolvedDocument->id === $document->id;
            })
            ->andReturn($stream);
        $mock->shouldReceive('previewMimeType')
            ->once()
            ->withArgs(function (Document $resolvedDocument) use ($document): bool {
                return $resolvedDocument->id === $document->id;
            })
            ->andReturn('application/pdf');
    });

    $response = $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->get(route('documents.preview', $document));

    $response->assertSuccessful()
        ->assertHeader('content-type', 'application/pdf')
        ->assertHeader('content-disposition', 'inline; filename="review-copy.pdf"');

    expect($response->streamedContent())->toBe('pdf-preview-body');
});
