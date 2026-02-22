<?php

use App\Models\Client;
use App\Models\Document;
use App\Models\Matter;
use App\Models\Tenant;
use App\Models\User;
use App\Policies\DocumentPolicy;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\PermissionRegistrar;

beforeEach(function () {
    (new RolesAndPermissionsSeeder)->run();
    app()[PermissionRegistrar::class]->forgetCachedPermissions();
});

afterEach(function () {
    tenancy()->end();
});

function createDocumentAuthContext(string $role): array
{
    $tenant = Tenant::factory()->create();
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

    setPermissionsTeamId($tenant->id);
    $user->assignRole($role);

    return [$tenant, $user, $matter, $document];
}

dataset('document-role-matrix', [
    'tenant-admin' => ['tenant-admin', true, true, true, true, true],
    'partner' => ['partner', true, true, true, false, true],
    'associate' => ['associate', true, true, true, false, false],
    'client' => ['client', true, false, false, false, false],
]);

test('roles can or cannot view document list based on permission', function (
    string $role,
    bool $canView,
): void {
    [$tenant, $user] = createDocumentAuthContext($role);

    $response = $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->get(route('documents.index'));

    $canView ? $response->assertSuccessful() : $response->assertForbidden();
})->with('document-role-matrix');

test('roles can or cannot upload documents based on permission', function (
    string $role,
    bool $canView,
    bool $canCreate,
): void {
    [$tenant, $user, $matter] = createDocumentAuthContext($role);
    tenancy()->initialize($tenant);
    Storage::fake('s3');

    $response = $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->post(route('matters.documents.store', $matter), [
            'title' => 'Authorization Upload',
            'file' => UploadedFile::fake()->create('authorization.pdf', 128, 'application/pdf'),
        ]);

    $canCreate ? $response->assertRedirect() : $response->assertForbidden();
})->with('document-role-matrix');

test('roles can or cannot update documents based on permission', function (
    string $role,
    bool $canView,
    bool $canCreate,
    bool $canUpdate,
): void {
    [$tenant, $user, $matter, $document] = createDocumentAuthContext($role);
    tenancy()->initialize($tenant);

    $response = $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->put(route('documents.update', $document), [
            'title' => 'Changed by '.$role,
        ]);

    $canUpdate ? $response->assertRedirect() : $response->assertForbidden();
})->with('document-role-matrix');

test('roles can or cannot delete documents based on permission', function (
    string $role,
    bool $canView,
    bool $canCreate,
    bool $canUpdate,
    bool $canDelete,
): void {
    [$tenant, $user, $matter, $document] = createDocumentAuthContext($role);
    tenancy()->initialize($tenant);
    Storage::fake('s3');

    Storage::disk('s3')->put($document->file_path, 'test');

    $response = $this->actingAs($user)
        ->withHeaders(['X-Tenant-ID' => $tenant->id])
        ->delete(route('documents.destroy', $document));

    $canDelete ? $response->assertRedirect() : $response->assertForbidden();
})->with('document-role-matrix');

test('approve permission is enforced by policy', function (
    string $role,
    bool $canView,
    bool $canCreate,
    bool $canUpdate,
    bool $canDelete,
    bool $canApprove,
): void {
    [$tenant, $user, $matter, $document] = createDocumentAuthContext($role);
    tenancy()->initialize($tenant);

    $policy = app(DocumentPolicy::class);

    expect($policy->approve($user, $document))->toBe($canApprove);
})->with('document-role-matrix');
