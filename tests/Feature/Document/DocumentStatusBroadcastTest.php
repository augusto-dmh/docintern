<?php

use App\Events\DocumentStatusUpdated;
use App\Models\Client;
use App\Models\Document;
use App\Models\Matter;
use App\Models\Tenant;
use App\Models\User;
use App\Services\DocumentStatusTransitionService;
use App\Services\DocumentUploadService;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    (new RolesAndPermissionsSeeder)->run();
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
});

afterEach(function (): void {
    tenancy()->end();
});

test('upload emits document status updated broadcast event', function (): void {
    Event::fake([DocumentStatusUpdated::class]);
    Storage::fake('s3');

    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create();
    $client = Client::factory()->create(['tenant_id' => $tenant->id]);
    $matter = Matter::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
    ]);

    setPermissionsTeamId($tenant->id);
    $user->assignRole('tenant-admin');
    tenancy()->initialize($tenant);

    /** @var DocumentUploadService $uploadService */
    $uploadService = app(DocumentUploadService::class);

    $document = $uploadService->upload(
        UploadedFile::fake()->create('retainer.pdf', 128, 'application/pdf'),
        $matter,
        $user,
        'Retainer agreement',
    );

    Event::assertDispatched(
        DocumentStatusUpdated::class,
        fn (DocumentStatusUpdated $event): bool => $event->documentId === $document->id
            && $event->tenantId === $tenant->id
            && $event->statusFrom === null
            && $event->statusTo === 'uploaded'
            && $event->event === 'document.uploaded',
    );
});

test('status transition emits document status updated broadcast event', function (): void {
    Event::fake([DocumentStatusUpdated::class]);

    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create();
    $client = Client::factory()->create(['tenant_id' => $tenant->id]);
    $matter = Matter::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
    ]);
    $document = Document::factory()->readyForReview()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
        'uploaded_by' => $user->id,
    ]);

    setPermissionsTeamId($tenant->id);
    $user->assignRole('tenant-admin');
    tenancy()->initialize($tenant);

    /** @var DocumentStatusTransitionService $transitionService */
    $transitionService = app(DocumentStatusTransitionService::class);

    $transitionService->transition(
        document: $document,
        toStatus: 'reviewed',
        consumerName: 'test-suite',
    );

    Event::assertDispatched(
        DocumentStatusUpdated::class,
        fn (DocumentStatusUpdated $event): bool => $event->documentId === $document->id
            && $event->tenantId === $tenant->id
            && $event->statusFrom === 'ready_for_review'
            && $event->statusTo === 'reviewed'
            && $event->event === 'document.status.transitioned',
    );
});
