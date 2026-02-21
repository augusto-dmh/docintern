<?php

use App\Models\Client;
use App\Models\Matter;
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

function createMatterAuthContext(string $role): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create();
    $client = Client::factory()->create(['tenant_id' => $tenant->id]);
    $matter = Matter::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
    ]);

    setPermissionsTeamId($tenant->id);
    $user->assignRole($role);

    return [$tenant, $user, $client, $matter];
}

describe('tenant-admin', function () {
    test('can view matters list', function () {
        [$tenant, $user] = createMatterAuthContext('tenant-admin');

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->get(route('matters.index'))
            ->assertSuccessful();
    });

    test('can create a matter', function () {
        [$tenant, $user, $client] = createMatterAuthContext('tenant-admin');
        tenancy()->initialize($tenant);

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->post(route('matters.store'), [
                'client_id' => $client->id,
                'title' => 'New Matter',
                'status' => 'open',
            ])
            ->assertRedirect(route('matters.index'));
    });

    test('can edit a matter', function () {
        [$tenant, $user, $client, $matter] = createMatterAuthContext('tenant-admin');
        tenancy()->initialize($tenant);

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->put(route('matters.update', $matter), [
                'client_id' => $client->id,
                'title' => 'Updated',
                'status' => 'open',
            ])
            ->assertRedirect(route('matters.show', $matter));
    });

    test('can delete a matter', function () {
        [$tenant, $user, $client, $matter] = createMatterAuthContext('tenant-admin');
        tenancy()->initialize($tenant);

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->delete(route('matters.destroy', $matter))
            ->assertRedirect(route('matters.index'));
    });
});

describe('partner', function () {
    test('can view matters list', function () {
        [$tenant, $user] = createMatterAuthContext('partner');

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->get(route('matters.index'))
            ->assertSuccessful();
    });

    test('can create a matter', function () {
        [$tenant, $user, $client] = createMatterAuthContext('partner');
        tenancy()->initialize($tenant);

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->post(route('matters.store'), [
                'client_id' => $client->id,
                'title' => 'New Matter',
                'status' => 'open',
            ])
            ->assertRedirect(route('matters.index'));
    });

    test('can edit a matter', function () {
        [$tenant, $user, $client, $matter] = createMatterAuthContext('partner');
        tenancy()->initialize($tenant);

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->put(route('matters.update', $matter), [
                'client_id' => $client->id,
                'title' => 'Updated',
                'status' => 'open',
            ])
            ->assertRedirect(route('matters.show', $matter));
    });

    test('cannot delete a matter', function () {
        [$tenant, $user, $client, $matter] = createMatterAuthContext('partner');
        tenancy()->initialize($tenant);

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->delete(route('matters.destroy', $matter))
            ->assertForbidden();
    });
});

describe('associate', function () {
    test('can view matters list', function () {
        [$tenant, $user] = createMatterAuthContext('associate');

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->get(route('matters.index'))
            ->assertSuccessful();
    });

    test('can create a matter', function () {
        [$tenant, $user, $client] = createMatterAuthContext('associate');
        tenancy()->initialize($tenant);

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->post(route('matters.store'), [
                'client_id' => $client->id,
                'title' => 'New Matter',
                'status' => 'open',
            ])
            ->assertRedirect(route('matters.index'));
    });

    test('can edit a matter', function () {
        [$tenant, $user, $client, $matter] = createMatterAuthContext('associate');
        tenancy()->initialize($tenant);

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->put(route('matters.update', $matter), [
                'client_id' => $client->id,
                'title' => 'Updated',
                'status' => 'open',
            ])
            ->assertRedirect(route('matters.show', $matter));
    });

    test('cannot delete a matter', function () {
        [$tenant, $user, $client, $matter] = createMatterAuthContext('associate');
        tenancy()->initialize($tenant);

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->delete(route('matters.destroy', $matter))
            ->assertForbidden();
    });
});

describe('client role', function () {
    test('can view matters list', function () {
        [$tenant, $user] = createMatterAuthContext('client');

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->get(route('matters.index'))
            ->assertSuccessful();
    });

    test('can view a matter', function () {
        [$tenant, $user, $client, $matter] = createMatterAuthContext('client');
        tenancy()->initialize($tenant);

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->get(route('matters.show', $matter))
            ->assertSuccessful();
    });

    test('cannot create a matter', function () {
        [$tenant, $user, $client] = createMatterAuthContext('client');
        tenancy()->initialize($tenant);

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->post(route('matters.store'), [
                'client_id' => $client->id,
                'title' => 'New Matter',
                'status' => 'open',
            ])
            ->assertForbidden();
    });

    test('cannot edit a matter', function () {
        [$tenant, $user, $client, $matter] = createMatterAuthContext('client');
        tenancy()->initialize($tenant);

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->put(route('matters.update', $matter), [
                'client_id' => $client->id,
                'title' => 'Updated',
                'status' => 'open',
            ])
            ->assertForbidden();
    });

    test('cannot delete a matter', function () {
        [$tenant, $user, $client, $matter] = createMatterAuthContext('client');
        tenancy()->initialize($tenant);

        $this->actingAs($user)
            ->withHeaders(['X-Tenant-ID' => $tenant->id])
            ->delete(route('matters.destroy', $matter))
            ->assertForbidden();
    });
});
