<?php

declare(strict_types=1);

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

afterEach(function (): void {
    tenancy()->end();
    setPermissionsTeamId(null);
});

test('permission team columns use string-compatible types', function (): void {
    expectColumnTypeToBeStringCompatible('roles', 'tenant_id');
    expectColumnTypeToBeStringCompatible('model_has_roles', 'tenant_id');
    expectColumnTypeToBeStringCompatible('model_has_permissions', 'tenant_id');
});

test('roles and permissions seeder always writes global roles', function (): void {
    $tenant = Tenant::factory()->create();
    setPermissionsTeamId($tenant->id);

    (new RolesAndPermissionsSeeder)->run();

    $teamForeignKey = (string) config('permission.column_names.team_foreign_key', 'tenant_id');

    $nonGlobalRoleCount = Role::query()
        ->whereIn('name', ['super-admin', 'tenant-admin', 'partner', 'associate', 'client'])
        ->whereNotNull($teamForeignKey)
        ->count();

    expect($nonGlobalRoleCount)->toBe(0);
});

test('database seeder assigns user roles with explicit team context', function (): void {
    $this->seed(DatabaseSeeder::class);

    $tableNames = config('permission.table_names');
    $teamForeignKey = (string) config('permission.column_names.team_foreign_key', 'tenant_id');

    expect(is_array($tableNames))->toBeTrue();

    $tenantId = Tenant::query()->value('id');

    $assignedTeamIds = DB::table($tableNames['model_has_roles'])
        ->where('model_type', User::class)
        ->pluck($teamForeignKey)
        ->unique()
        ->values()
        ->all();

    expect($assignedTeamIds)->toHaveCount(1)
        ->and($assignedTeamIds[0])->toBe($tenantId);
});

function expectColumnTypeToBeStringCompatible(string $table, string $column): void
{
    $columnType = strtolower(Schema::getColumnType($table, $column));

    $isStringCompatibleType = $columnType === 'string'
        || $columnType === 'varchar'
        || $columnType === 'text'
        || str_contains($columnType, 'char');

    expect($isStringCompatibleType)->toBeTrue(
        sprintf('Expected %s.%s to be string compatible, received [%s].', $table, $column, $columnType),
    );
}
