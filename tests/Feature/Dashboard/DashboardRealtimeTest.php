<?php

use App\Models\Client;
use App\Models\Document;
use App\Models\Matter;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    (new RolesAndPermissionsSeeder)->run();
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
});

afterEach(function (): void {
    tenancy()->end();
});

test('dashboard shares realtime props for tenant scoped user', function (): void {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create();
    $client = Client::factory()->create(['tenant_id' => $tenant->id]);
    $matter = Matter::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
    ]);
    $uploadedDocument = Document::factory()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
        'uploaded_by' => $user->id,
        'status' => 'uploaded',
        'updated_at' => now(),
    ]);
    $scanningDocument = Document::factory()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
        'uploaded_by' => $user->id,
        'status' => 'scanning',
        'updated_at' => now()->subMinute(),
    ]);
    $extractingDocument = Document::factory()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
        'uploaded_by' => $user->id,
        'status' => 'extracting',
        'updated_at' => now()->subMinutes(2),
    ]);
    $readyForReviewDocument = Document::factory()->readyForReview()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
        'uploaded_by' => $user->id,
        'updated_at' => now()->subMinutes(3),
    ]);
    $approvedDocument = Document::factory()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
        'uploaded_by' => $user->id,
        'status' => 'approved',
        'updated_at' => now()->subMinutes(4),
    ]);
    $failedDocument = Document::factory()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
        'uploaded_by' => $user->id,
        'status' => 'scan_failed',
        'updated_at' => now()->subMinutes(5),
    ]);

    setPermissionsTeamId($tenant->id);
    $user->assignRole('tenant-admin');

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('realtimeTenantId', $tenant->id)
            ->where('stats.processed_today', 1)
            ->where('stats.pending_review', 1)
            ->where('stats.failed', 1)
            ->has('pipelineDocuments', 6)
            ->where('pipelineDocuments.0.id', $uploadedDocument->id)
            ->where('pipelineDocuments.0.status', 'uploaded')
            ->where('pipelineDocuments.1.id', $scanningDocument->id)
            ->where('pipelineDocuments.1.status', 'scanning')
            ->where('pipelineDocuments.2.id', $extractingDocument->id)
            ->where('pipelineDocuments.2.status', 'extracting')
            ->where('pipelineDocuments.3.id', $readyForReviewDocument->id)
            ->where('pipelineDocuments.3.status', 'ready_for_review')
            ->where('pipelineDocuments.4.id', $approvedDocument->id)
            ->where('pipelineDocuments.4.status', 'approved')
            ->where('pipelineDocuments.5.id', $failedDocument->id)
            ->where('pipelineDocuments.5.status', 'scan_failed')
        );
});

test('dashboard falls back to disabled realtime props for super admin without tenant context', function (): void {
    $tenant = Tenant::factory()->create();
    $superAdmin = User::factory()->forTenant($tenant)->create();

    setPermissionsTeamId($tenant->id);
    $superAdmin->assignRole('super-admin');

    $this->actingAs($superAdmin)
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('realtimeTenantId', null)
            ->where('stats.processed_today', 0)
            ->where('stats.pending_review', 0)
            ->where('stats.failed', 0)
            ->where('pipelineDocuments', [])
        );
});

test('dashboard uses selected tenant context for super admin realtime props', function (): void {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $superAdmin = User::factory()->forTenant($tenant)->create();
    $client = Client::factory()->create(['tenant_id' => $otherTenant->id]);
    $matter = Matter::factory()->create([
        'tenant_id' => $otherTenant->id,
        'client_id' => $client->id,
    ]);
    $selectedTenantDocument = Document::factory()->create([
        'tenant_id' => $otherTenant->id,
        'matter_id' => $matter->id,
        'uploaded_by' => null,
        'status' => 'classifying',
        'updated_at' => now(),
    ]);

    setPermissionsTeamId($tenant->id);
    $superAdmin->assignRole('super-admin');

    $this->actingAs($superAdmin)
        ->withSession(['active_tenant_id' => $otherTenant->id])
        ->get(route('dashboard'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Dashboard')
            ->where('realtimeTenantId', $otherTenant->id)
            ->where('pipelineDocuments.0.id', $selectedTenantDocument->id)
            ->where('pipelineDocuments.0.status', 'classifying')
        );
});
