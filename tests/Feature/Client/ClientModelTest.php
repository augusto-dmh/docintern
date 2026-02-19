<?php

use App\Models\Client;
use App\Models\Tenant;

afterEach(function () {
    tenancy()->end();
});

test('client factory creates a valid client', function () {
    $tenant = Tenant::factory()->create();

    $client = Client::factory()->create(['tenant_id' => $tenant->id]);

    expect($client->tenant_id)->toBe($tenant->id)
        ->and($client->name)->not->toBeEmpty();
});

test('client belongs to a tenant', function () {
    $tenant = Tenant::factory()->create();

    $client = Client::factory()->create(['tenant_id' => $tenant->id]);

    expect($client->tenant->id)->toBe($tenant->id);
});

test('client has many matters relationship', function () {
    $tenant = Tenant::factory()->create();

    $client = Client::factory()->create(['tenant_id' => $tenant->id]);

    expect($client->matters())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
});

test('client query is scoped to current tenant', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    Client::factory()->count(2)->create(['tenant_id' => $tenantA->id]);
    Client::factory()->count(3)->create(['tenant_id' => $tenantB->id]);

    tenancy()->initialize($tenantA);
    expect(Client::count())->toBe(2);

    tenancy()->initialize($tenantB);
    expect(Client::count())->toBe(3);
});
