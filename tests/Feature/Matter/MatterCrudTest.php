<?php

use App\Models\Client;
use App\Models\Document;
use App\Models\Matter;
use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
});

afterEach(function () {
    tenancy()->end();
});

function createMatterCrudContext(): array
{
    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create();
    $client = Client::factory()->create(['tenant_id' => $tenant->id]);

    setPermissionsTeamId($tenant->id);
    $user->assignRole('tenant-admin');

    return [$tenant, $user, $client];
}

test('matter index page can be rendered', function () {
    [$tenant, $user] = createMatterCrudContext();

    $response = $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->get(route('matters.index'));

    $response->assertSuccessful();
});

test('matter create page can be rendered', function () {
    [$tenant, $user] = createMatterCrudContext();

    $response = $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->get(route('matters.create'));

    $response->assertSuccessful();
});

test('matter can be stored', function () {
    [$tenant, $user, $client] = createMatterCrudContext();
    tenancy()->initialize($tenant);

    $response = $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->post(route('matters.store'), [
            'client_id' => $client->id,
            'title' => 'Test Matter',
            'description' => 'A test matter',
            'status' => 'open',
        ]);

    $response->assertRedirect(route('matters.index'));
    expect(Matter::where('title', 'Test Matter')->exists())->toBeTrue();
});

test('matter store validates required fields', function () {
    [$tenant, $user] = createMatterCrudContext();
    tenancy()->initialize($tenant);

    $response = $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->post(route('matters.store'), []);

    $response->assertSessionHasErrors(['client_id', 'title', 'status']);
});

test('matter show page can be rendered', function () {
    [$tenant, $user, $client] = createMatterCrudContext();
    $matter = Matter::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
    ]);
    $document = Document::factory()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
        'uploaded_by' => $user->id,
    ]);

    tenancy()->initialize($tenant);

    $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->get(route('matters.show', $matter))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('matters/Show')
            ->where('matter.id', $matter->id)
            ->where('matter.documents.0.id', $document->id)
        );
});

test('matter edit page can be rendered', function () {
    [$tenant, $user, $client] = createMatterCrudContext();
    $matter = Matter::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
    ]);
    tenancy()->initialize($tenant);

    $response = $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->get(route('matters.edit', $matter));

    $response->assertSuccessful();
});

test('matter can be updated', function () {
    [$tenant, $user, $client] = createMatterCrudContext();
    $matter = Matter::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
    ]);
    tenancy()->initialize($tenant);

    $response = $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->put(route('matters.update', $matter), [
            'client_id' => $client->id,
            'title' => 'Updated Matter',
            'status' => 'closed',
        ]);

    $response->assertRedirect(route('matters.show', $matter));
    expect($matter->fresh()->title)->toBe('Updated Matter');
});

test('matter can be destroyed', function () {
    [$tenant, $user, $client] = createMatterCrudContext();
    $matter = Matter::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
    ]);
    tenancy()->initialize($tenant);

    $response = $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->delete(route('matters.destroy', $matter));

    $response->assertRedirect(route('matters.index'));
    expect(Matter::find($matter->id))->toBeNull();
});
