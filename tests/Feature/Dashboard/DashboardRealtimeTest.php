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
    $approvedDocument = Document::factory()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
        'uploaded_by' => $user->id,
        'status' => 'approved',
        'updated_at' => now(),
    ]);
    $pendingDocument = Document::factory()->readyForReview()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
        'uploaded_by' => $user->id,
        'updated_at' => now()->subMinutes(2),
    ]);
    Document::factory()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
        'uploaded_by' => $user->id,
        'status' => 'scan_failed',
        'updated_at' => now()->subMinutes(3),
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
            ->has('pipelineDocuments')
            ->where('pipelineDocuments.0.id', $approvedDocument->id)
            ->where('pipelineDocuments.1.id', $pendingDocument->id)
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
