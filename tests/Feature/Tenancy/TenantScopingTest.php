<?php

use App\Models\Client;
use App\Models\Document;
use App\Models\Matter;
use App\Models\Tenant;
use App\Models\User;

afterEach(function () {
    tenancy()->end();
});

test('clients are scoped to current tenant', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    Client::factory()->count(3)->create(['tenant_id' => $tenantA->id]);
    Client::factory()->count(2)->create(['tenant_id' => $tenantB->id]);

    tenancy()->initialize($tenantA);
    expect(Client::count())->toBe(3);

    tenancy()->initialize($tenantB);
    expect(Client::count())->toBe(2);
});

test('matters are scoped to current tenant', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $clientA = Client::factory()->create(['tenant_id' => $tenantA->id]);
    $clientB = Client::factory()->create(['tenant_id' => $tenantB->id]);

    Matter::factory()->count(4)->create([
        'tenant_id' => $tenantA->id,
        'client_id' => $clientA->id,
    ]);
    Matter::factory()->count(1)->create([
        'tenant_id' => $tenantB->id,
        'client_id' => $clientB->id,
    ]);

    tenancy()->initialize($tenantA);
    expect(Matter::count())->toBe(4);

    tenancy()->initialize($tenantB);
    expect(Matter::count())->toBe(1);
});

test('tenant user cannot access other tenant client via http', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $userA = User::factory()->forTenant($tenantA)->create();
    $clientB = Client::factory()->create(['tenant_id' => $tenantB->id]);

    tenancy()->initialize($tenantA);

    $response = $this->actingAs($userA)
        ->withHeaders(['X-Tenant-ID' => $tenantA->id])
        ->get(route('clients.show', $clientB));

    $response->assertNotFound();
});

test('tenant user cannot access other tenant matter via http', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $userA = User::factory()->forTenant($tenantA)->create();
    $clientB = Client::factory()->create(['tenant_id' => $tenantB->id]);
    $matterB = Matter::factory()->create([
        'tenant_id' => $tenantB->id,
        'client_id' => $clientB->id,
    ]);

    tenancy()->initialize($tenantA);

    $response = $this->actingAs($userA)
        ->withHeaders(['X-Tenant-ID' => $tenantA->id])
        ->get(route('matters.show', $matterB));

    $response->assertNotFound();
});

test('tenant user cannot access other tenant document via http', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();
    $userA = User::factory()->forTenant($tenantA)->create();
    $clientB = Client::factory()->create(['tenant_id' => $tenantB->id]);
    $matterB = Matter::factory()->create([
        'tenant_id' => $tenantB->id,
        'client_id' => $clientB->id,
    ]);
    $documentB = Document::factory()->create([
        'tenant_id' => $tenantB->id,
        'matter_id' => $matterB->id,
    ]);

    tenancy()->initialize($tenantA);

    $response = $this->actingAs($userA)
        ->withHeaders(['X-Tenant-ID' => $tenantA->id])
        ->get(route('documents.show', $documentB));

    $response->assertNotFound();
});
