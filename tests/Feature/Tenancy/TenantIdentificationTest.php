<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    (new RolesAndPermissionsSeeder)->run();
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
});

afterEach(function (): void {
    tenancy()->end();
    setPermissionsTeamId(null);
});

test('tenant users can access tenant routes without tenant header', function (): void {
    $tenant = Tenant::factory()->create();
    $tenantUser = createTenantAdmin($tenant);

    $response = $this->actingAs($tenantUser)
        ->get(route('clients.index'));

    $response->assertSuccessful();
});

test('super-admin without selected tenant context cannot access tenant routes', function (): void {
    $tenant = Tenant::factory()->create();
    $superAdmin = createSuperAdminForTenant($tenant);

    $response = $this->actingAs($superAdmin)
        ->get(route('clients.index'));

    $response->assertForbidden();
});

test('super-admin can access tenant routes with selected tenant context', function (): void {
    $tenant = Tenant::factory()->create();
    $superAdmin = createSuperAdminForTenant($tenant);

    $response = $this->actingAs($superAdmin)
        ->withSession(['active_tenant_id' => $tenant->id])
        ->get(route('clients.index'));

    $response->assertSuccessful();
});

test('header fallback resolves tenant in testing when no higher-priority context exists', function (): void {
    $tenant = Tenant::factory()->create();
    $superAdmin = createSuperAdminForTenant($tenant);

    $response = $this->actingAs($superAdmin)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->get(route('clients.index'));

    $response->assertSuccessful();
});

test('cross-tenant domain resolution is denied for regular tenant users', function (): void {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $tenantUser = createTenantAdmin($tenantA);

    $tenantB->domains()->create([
        'domain' => 'tenant-b.localhost',
    ]);

    $response = $this->actingAs($tenantUser)
        ->get('http://tenant-b.localhost/clients');

    $response->assertForbidden();
});

test('invalid header fallback value is denied', function (): void {
    $tenant = Tenant::factory()->create();
    $superAdmin = createSuperAdminForTenant($tenant);

    $response = $this->actingAs($superAdmin)
        ->withHeaders(['X-Tenant-ID' => 'nonexistent-tenant'])
        ->get(route('clients.index'));

    $response->assertForbidden();
});

function createTenantAdmin(Tenant $tenant): User
{
    $user = User::factory()->forTenant($tenant)->create();

    setPermissionsTeamId($tenant->id);
    $user->assignRole('tenant-admin');
    setPermissionsTeamId(null);

    return $user;
}

function createSuperAdminForTenant(Tenant $tenant): User
{
    $superAdmin = User::factory()->create();

    setPermissionsTeamId($tenant->id);
    $superAdmin->assignRole('super-admin');
    setPermissionsTeamId(null);

    return $superAdmin;
}
