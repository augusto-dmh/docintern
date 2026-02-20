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

function createClientCrudContext(): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create();

    setPermissionsTeamId($tenant->id);
    $user->assignRole('tenant-admin');

    return [$tenant, $user];
}

test('client index page can be rendered', function () {
    [$tenant, $user] = createClientCrudContext();

    $response = $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->get(route('clients.index'));

    $response->assertSuccessful();
});

test('client create page can be rendered', function () {
    [$tenant, $user] = createClientCrudContext();

    $response = $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->get(route('clients.create'));

    $response->assertSuccessful();
});

test('client can be stored', function () {
    [$tenant, $user] = createClientCrudContext();
    tenancy()->initialize($tenant);

    $response = $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->post(route('clients.store'), [
            'name' => 'Test Client',
            'email' => 'client@example.com',
            'phone' => '555-1234',
            'company' => 'Test Corp',
            'notes' => 'Some notes',
        ]);

    $response->assertRedirect(route('clients.index'));
    expect(Client::where('name', 'Test Client')->exists())->toBeTrue();
});

test('client store validates required fields', function () {
    [$tenant, $user] = createClientCrudContext();
    tenancy()->initialize($tenant);

    $response = $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->post(route('clients.store'), []);

    $response->assertSessionHasErrors(['name']);
});

test('client show page can be rendered', function () {
    [$tenant, $user] = createClientCrudContext();
    $client = Client::factory()->create(['tenant_id' => $tenant->id]);
    tenancy()->initialize($tenant);

    $response = $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->get(route('clients.show', $client));

    $response->assertSuccessful();
});

test('client edit page can be rendered', function () {
    [$tenant, $user] = createClientCrudContext();
    $client = Client::factory()->create(['tenant_id' => $tenant->id]);
    tenancy()->initialize($tenant);

    $response = $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->get(route('clients.edit', $client));

    $response->assertSuccessful();
});

test('client can be updated', function () {
    [$tenant, $user] = createClientCrudContext();
    $client = Client::factory()->create(['tenant_id' => $tenant->id]);
    tenancy()->initialize($tenant);

    $response = $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->put(route('clients.update', $client), [
            'name' => 'Updated Client',
            'email' => 'updated@example.com',
        ]);

    $response->assertRedirect(route('clients.show', $client));
    expect($client->fresh()->name)->toBe('Updated Client');
});

test('client can be destroyed', function () {
    [$tenant, $user] = createClientCrudContext();
    $client = Client::factory()->create(['tenant_id' => $tenant->id]);
    tenancy()->initialize($tenant);

    $response = $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->delete(route('clients.destroy', $client));

    $response->assertRedirect(route('clients.index'));
    expect(Client::find($client->id))->toBeNull();
});
