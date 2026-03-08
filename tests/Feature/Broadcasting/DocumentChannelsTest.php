<?php

use App\Models\Client;
use App\Models\Document;
use App\Models\Matter;
use App\Models\Tenant;
use App\Models\User;
use App\Support\BroadcastChannelAuthorizer;
use Database\Seeders\RolesAndPermissionsSeeder;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function (): void {
    (new RolesAndPermissionsSeeder)->run();
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
});

afterEach(function (): void {
    tenancy()->end();
});

test('tenant user can access only own tenant document channels', function (): void {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $user = User::factory()->forTenant($tenant)->create();

    $client = Client::factory()->create(['tenant_id' => $tenant->id]);
    $matter = Matter::factory()->create([
        'tenant_id' => $tenant->id,
        'client_id' => $client->id,
    ]);
    $document = Document::factory()->create([
        'tenant_id' => $tenant->id,
        'matter_id' => $matter->id,
        'uploaded_by' => $user->id,
    ]);

    $otherClient = Client::factory()->create(['tenant_id' => $otherTenant->id]);
    $otherMatter = Matter::factory()->create([
        'tenant_id' => $otherTenant->id,
        'client_id' => $otherClient->id,
    ]);
    $foreignDocument = Document::factory()->create([
        'tenant_id' => $otherTenant->id,
        'matter_id' => $otherMatter->id,
        'uploaded_by' => null,
    ]);

    setPermissionsTeamId($tenant->id);
    $user->assignRole('tenant-admin');

    /** @var BroadcastChannelAuthorizer $channelAuthorizer */
    $channelAuthorizer = app(BroadcastChannelAuthorizer::class);

    expect($channelAuthorizer->canAccessTenantDocumentsChannel($user, $tenant->id))->toBeTrue()
        ->and($channelAuthorizer->canAccessTenantDocumentsChannel($user, $otherTenant->id))->toBeFalse()
        ->and($channelAuthorizer->canAccessDocumentChannel($user, $document->id))->toBeTrue()
        ->and($channelAuthorizer->canAccessDocumentChannel($user, $foreignDocument->id))->toBeFalse();
});

test('super admin tenant channel access follows selected tenant session context', function (): void {
    $tenant = Tenant::factory()->create();
    $otherTenant = Tenant::factory()->create();
    $superAdmin = User::factory()->forTenant($tenant)->create();

    setPermissionsTeamId($tenant->id);
    $superAdmin->assignRole('super-admin');

    /** @var BroadcastChannelAuthorizer $channelAuthorizer */
    $channelAuthorizer = app(BroadcastChannelAuthorizer::class);

    expect($channelAuthorizer->canAccessTenantDocumentsChannel($superAdmin, $tenant->id))->toBeFalse();

    session()->put('active_tenant_id', $tenant->id);

    expect($channelAuthorizer->canAccessTenantDocumentsChannel($superAdmin, $tenant->id))->toBeTrue()
        ->and($channelAuthorizer->canAccessTenantDocumentsChannel($superAdmin, $otherTenant->id))->toBeFalse();
});
