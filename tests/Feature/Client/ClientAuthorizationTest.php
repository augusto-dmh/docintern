<?php

use App\Models\Client;
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

function createClientAuthContext(string $role): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create();
    $client = Client::factory()->create(['tenant_id' => $tenant->id]);

    setPermissionsTeamId($tenant->id);
    $user->assignRole($role);

    return [$tenant, $user, $client];
}

describe('tenant-admin', function () {
    test('can view clients list', function () {
        [$tenant, $user] = createClientAuthContext('tenant-admin');

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->get(route('clients.index'))
            ->assertSuccessful();
    });

    test('can create a client', function () {
        [$tenant, $user] = createClientAuthContext('tenant-admin');
        tenancy()->initialize($tenant);

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->post(route('clients.store'), ['name' => 'New Client'])
            ->assertRedirect(route('clients.index'));
    });

    test('can edit a client', function () {
        [$tenant, $user, $client] = createClientAuthContext('tenant-admin');
        tenancy()->initialize($tenant);

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->put(route('clients.update', $client), ['name' => 'Updated'])
            ->assertRedirect(route('clients.show', $client));
    });

    test('can delete a client', function () {
        [$tenant, $user, $client] = createClientAuthContext('tenant-admin');
        tenancy()->initialize($tenant);

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->delete(route('clients.destroy', $client))
            ->assertRedirect(route('clients.index'));
    });
});

describe('partner', function () {
    test('can view clients list', function () {
        [$tenant, $user] = createClientAuthContext('partner');

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->get(route('clients.index'))
            ->assertSuccessful();
    });

    test('can create a client', function () {
        [$tenant, $user] = createClientAuthContext('partner');
        tenancy()->initialize($tenant);

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->post(route('clients.store'), ['name' => 'New Client'])
            ->assertRedirect(route('clients.index'));
    });

    test('can edit a client', function () {
        [$tenant, $user, $client] = createClientAuthContext('partner');
        tenancy()->initialize($tenant);

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->put(route('clients.update', $client), ['name' => 'Updated'])
            ->assertRedirect(route('clients.show', $client));
    });

    test('cannot delete a client', function () {
        [$tenant, $user, $client] = createClientAuthContext('partner');
        tenancy()->initialize($tenant);

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->delete(route('clients.destroy', $client))
            ->assertForbidden();
    });
});

describe('associate', function () {
    test('can view clients list', function () {
        [$tenant, $user] = createClientAuthContext('associate');

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->get(route('clients.index'))
            ->assertSuccessful();
    });

    test('can create a client', function () {
        [$tenant, $user] = createClientAuthContext('associate');
        tenancy()->initialize($tenant);

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->post(route('clients.store'), ['name' => 'New Client'])
            ->assertRedirect(route('clients.index'));
    });

    test('can edit a client', function () {
        [$tenant, $user, $client] = createClientAuthContext('associate');
        tenancy()->initialize($tenant);

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->put(route('clients.update', $client), ['name' => 'Updated'])
            ->assertRedirect(route('clients.show', $client));
    });

    test('cannot delete a client', function () {
        [$tenant, $user, $client] = createClientAuthContext('associate');
        tenancy()->initialize($tenant);

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->delete(route('clients.destroy', $client))
            ->assertForbidden();
    });
});

describe('client role', function () {
    test('can view clients list', function () {
        [$tenant, $user] = createClientAuthContext('client');

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->get(route('clients.index'))
            ->assertSuccessful();
    });

    test('can view a client', function () {
        [$tenant, $user, $client] = createClientAuthContext('client');
        tenancy()->initialize($tenant);

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->get(route('clients.show', $client))
            ->assertSuccessful();
    });

    test('cannot create a client', function () {
        [$tenant, $user] = createClientAuthContext('client');
        tenancy()->initialize($tenant);

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->post(route('clients.store'), ['name' => 'New Client'])
            ->assertForbidden();
    });

    test('cannot edit a client', function () {
        [$tenant, $user, $client] = createClientAuthContext('client');
        tenancy()->initialize($tenant);

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->put(route('clients.update', $client), ['name' => 'Updated'])
            ->assertForbidden();
    });

    test('cannot delete a client', function () {
        [$tenant, $user, $client] = createClientAuthContext('client');
        tenancy()->initialize($tenant);

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->delete(route('clients.destroy', $client))
            ->assertForbidden();
    });
});
