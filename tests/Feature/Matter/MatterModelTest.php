<?php

use App\Models\Client;
use App\Models\Matter;
use App\Models\Tenant;

afterEach(function () {
    tenancy()->end();
});

test('matter factory creates a valid matter', function () {
    $tenant = Tenant::factory()->create();
    $client = Client::factory()->create(['tenant_id' => $tenant->id]);

    $matter = Matter::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

    expect($matter->tenant_id)->toBe($tenant->id)
        ->and($matter->client_id)->toBe($client->id)
        ->and($matter->status)->toBe('open');
});

test('matter belongs to a client', function () {
    $tenant = Tenant::factory()->create();
    $client = Client::factory()->create(['tenant_id' => $tenant->id]);

    $matter = Matter::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

    expect($matter->client->id)->toBe($client->id);
});

test('matter has many documents relationship', function () {
    $tenant = Tenant::factory()->create();
    $client = Client::factory()->create(['tenant_id' => $tenant->id]);
    $matter = Matter::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

    expect($matter->documents())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
});

test('matter closed state sets status to closed', function () {
    $tenant = Tenant::factory()->create();
    $client = Client::factory()->create(['tenant_id' => $tenant->id]);

    $matter = Matter::factory()->closed()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

    expect($matter->status)->toBe('closed');
});

test('matter onHold state sets status to on_hold', function () {
    $tenant = Tenant::factory()->create();
    $client = Client::factory()->create(['tenant_id' => $tenant->id]);

    $matter = Matter::factory()->onHold()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

    expect($matter->status)->toBe('on_hold');
});

test('matter query is scoped to current tenant', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $clientA = Client::factory()->create(['tenant_id' => $tenantA->id]);
    $clientB = Client::factory()->create(['tenant_id' => $tenantB->id]);

    Matter::factory()->count(2)->create(['tenant_id' => $tenantA->id, 'client_id' => $clientA->id]);
    Matter::factory()->count(4)->create(['tenant_id' => $tenantB->id, 'client_id' => $clientB->id]);

    tenancy()->initialize($tenantA);
    expect(Matter::count())->toBe(2);

    tenancy()->initialize($tenantB);
    expect(Matter::count())->toBe(4);
});
