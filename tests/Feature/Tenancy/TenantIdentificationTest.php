<?php

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
});

afterEach(function () {
    tenancy()->end();
});

test('requests with valid tenant header initialize tenancy', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create();

    setPermissionsTeamId($tenant->id);
    $user->assignRole('tenant-admin');

    $response = $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->get(route('clients.index'));

    $response->assertSuccessful();
});

test('requests without tenant header to tenant routes fail', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->get(route('clients.index'));

    $response->assertStatus(500);
});

test('requests with invalid tenant header fail', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => 'nonexistent-tenant'])
        ->get(route('clients.index'));

    $response->assertStatus(500);
});
