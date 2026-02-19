<?php

use App\Models\Client;
use App\Models\Document;
use App\Models\Matter;
use App\Models\Tenant;
use App\Models\User;

afterEach(function () {
    tenancy()->end();
});

test('document factory creates a valid document', function () {
    $tenant = Tenant::factory()->create();
    $client = Client::factory()->create(['tenant_id' => $tenant->id]);
    $matter = Matter::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

    $document = Document::factory()->create(['tenant_id' => $tenant->id, 'matter_id' => $matter->id]);

    expect($document->tenant_id)->toBe($tenant->id)
        ->and($document->matter_id)->toBe($matter->id)
        ->and($document->status)->toBe('uploaded');
});

test('document belongs to a matter', function () {
    $tenant = Tenant::factory()->create();
    $client = Client::factory()->create(['tenant_id' => $tenant->id]);
    $matter = Matter::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

    $document = Document::factory()->create(['tenant_id' => $tenant->id, 'matter_id' => $matter->id]);

    expect($document->matter->id)->toBe($matter->id);
});

test('document uploader relationship resolves to user', function () {
    $tenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create();
    $client = Client::factory()->create(['tenant_id' => $tenant->id]);
    $matter = Matter::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

    $document = Document::factory()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
        'uploaded_by' => $user->id,
    ]);

    expect($document->uploader->id)->toBe($user->id);
});

test('document approved state sets status to approved', function () {
    $tenant = Tenant::factory()->create();
    $client = Client::factory()->create(['tenant_id' => $tenant->id]);
    $matter = Matter::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

    $document = Document::factory()->approved()->create(['tenant_id' => $tenant->id, 'matter_id' => $matter->id]);

    expect($document->status)->toBe('approved');
});

test('document readyForReview state sets status to ready_for_review', function () {
    $tenant = Tenant::factory()->create();
    $client = Client::factory()->create(['tenant_id' => $tenant->id]);
    $matter = Matter::factory()->create(['tenant_id' => $tenant->id, 'client_id' => $client->id]);

    $document = Document::factory()->readyForReview()->create(['tenant_id' => $tenant->id, 'matter_id' => $matter->id]);

    expect($document->status)->toBe('ready_for_review');
});

test('document query is scoped to current tenant', function () {
    $tenantA = Tenant::factory()->create();
    $tenantB = Tenant::factory()->create();

    $clientA = Client::factory()->create(['tenant_id' => $tenantA->id]);
    $matterA = Matter::factory()->create(['tenant_id' => $tenantA->id, 'client_id' => $clientA->id]);

    $clientB = Client::factory()->create(['tenant_id' => $tenantB->id]);
    $matterB = Matter::factory()->create(['tenant_id' => $tenantB->id, 'client_id' => $clientB->id]);

    Document::factory()->count(2)->create(['tenant_id' => $tenantA->id, 'matter_id' => $matterA->id]);
    Document::factory()->count(5)->create(['tenant_id' => $tenantB->id, 'matter_id' => $matterB->id]);

    tenancy()->initialize($tenantA);
    expect(Document::count())->toBe(2);

    tenancy()->initialize($tenantB);
    expect(Document::count())->toBe(5);
});
