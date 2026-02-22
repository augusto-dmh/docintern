<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! config('permission.teams')) {
            return;
        }

        [$tableNames, $columnNames] = $this->getPermissionConfiguration();

        $this->alterRolesTeamColumnToString(
            rolesTable: $tableNames['roles'],
            teamForeignKey: $columnNames['team_foreign_key'],
        );

        $this->alterPivotTeamColumnToString(
            tableName: $tableNames['model_has_roles'],
            teamForeignKey: $columnNames['team_foreign_key'],
            pivotKey: $columnNames['role_pivot_key'] ?? 'role_id',
            modelMorphKey: $columnNames['model_morph_key'],
            primaryKeyName: 'model_has_roles_role_model_type_primary',
            indexName: 'model_has_roles_team_foreign_key_index',
        );

        $this->alterPivotTeamColumnToString(
            tableName: $tableNames['model_has_permissions'],
            teamForeignKey: $columnNames['team_foreign_key'],
            pivotKey: $columnNames['permission_pivot_key'] ?? 'permission_id',
            modelMorphKey: $columnNames['model_morph_key'],
            primaryKeyName: 'model_has_permissions_permission_model_type_primary',
            indexName: 'model_has_permissions_team_foreign_key_index',
        );
    }

    public function down(): void
    {
        if (! config('permission.teams')) {
            return;
        }

        [$tableNames, $columnNames] = $this->getPermissionConfiguration();

        $this->alterRolesTeamColumnToUnsignedBigInteger(
            rolesTable: $tableNames['roles'],
            teamForeignKey: $columnNames['team_foreign_key'],
        );

        $this->alterPivotTeamColumnToUnsignedBigInteger(
            tableName: $tableNames['model_has_roles'],
            teamForeignKey: $columnNames['team_foreign_key'],
            pivotKey: $columnNames['role_pivot_key'] ?? 'role_id',
            modelMorphKey: $columnNames['model_morph_key'],
            primaryKeyName: 'model_has_roles_role_model_type_primary',
            indexName: 'model_has_roles_team_foreign_key_index',
        );

        $this->alterPivotTeamColumnToUnsignedBigInteger(
            tableName: $tableNames['model_has_permissions'],
            teamForeignKey: $columnNames['team_foreign_key'],
            pivotKey: $columnNames['permission_pivot_key'] ?? 'permission_id',
            modelMorphKey: $columnNames['model_morph_key'],
            primaryKeyName: 'model_has_permissions_permission_model_type_primary',
            indexName: 'model_has_permissions_team_foreign_key_index',
        );
    }

    /**
     * @return array{0: array<string, string>, 1: array<string, string>}
     */
    protected function getPermissionConfiguration(): array
    {
        $tableNames = config('permission.table_names');
        $columnNames = config('permission.column_names');

        throw_if(
            ! is_array($tableNames) || ! isset($tableNames['roles'], $tableNames['model_has_roles'], $tableNames['model_has_permissions']),
            new RuntimeException('Permission table names are not configured.'),
        );

        throw_if(
            ! is_array($columnNames) || ! isset($columnNames['team_foreign_key'], $columnNames['model_morph_key']),
            new RuntimeException('Permission column names are not configured.'),
        );

        return [$tableNames, $columnNames];
    }

    protected function alterRolesTeamColumnToString(string $rolesTable, string $teamForeignKey): void
    {
        Schema::table($rolesTable, function (Blueprint $table) use ($teamForeignKey): void {
            $table->dropUnique([$teamForeignKey, 'name', 'guard_name']);
            $table->dropIndex('roles_team_foreign_key_index');
        });

        Schema::table($rolesTable, function (Blueprint $table) use ($teamForeignKey): void {
            $table->string($teamForeignKey)->nullable()->change();
        });

        Schema::table($rolesTable, function (Blueprint $table) use ($teamForeignKey): void {
            $table->index($teamForeignKey, 'roles_team_foreign_key_index');
            $table->unique([$teamForeignKey, 'name', 'guard_name']);
        });
    }

    protected function alterPivotTeamColumnToString(
        string $tableName,
        string $teamForeignKey,
        string $pivotKey,
        string $modelMorphKey,
        string $primaryKeyName,
        string $indexName,
    ): void {
        Schema::table($tableName, function (Blueprint $table) use ($primaryKeyName, $indexName): void {
            $table->dropPrimary($primaryKeyName);
            $table->dropIndex($indexName);
        });

        Schema::table($tableName, function (Blueprint $table) use ($teamForeignKey): void {
            $table->string($teamForeignKey)->change();
        });

        Schema::table($tableName, function (Blueprint $table) use ($teamForeignKey, $pivotKey, $modelMorphKey, $primaryKeyName, $indexName): void {
            $table->index($teamForeignKey, $indexName);
            $table->primary([$teamForeignKey, $pivotKey, $modelMorphKey, 'model_type'], $primaryKeyName);
        });
    }

    protected function alterRolesTeamColumnToUnsignedBigInteger(string $rolesTable, string $teamForeignKey): void
    {
        Schema::table($rolesTable, function (Blueprint $table) use ($teamForeignKey): void {
            $table->dropUnique([$teamForeignKey, 'name', 'guard_name']);
            $table->dropIndex('roles_team_foreign_key_index');
        });

        Schema::table($rolesTable, function (Blueprint $table) use ($teamForeignKey): void {
            $table->unsignedBigInteger($teamForeignKey)->nullable()->change();
        });

        Schema::table($rolesTable, function (Blueprint $table) use ($teamForeignKey): void {
            $table->index($teamForeignKey, 'roles_team_foreign_key_index');
            $table->unique([$teamForeignKey, 'name', 'guard_name']);
        });
    }

    protected function alterPivotTeamColumnToUnsignedBigInteger(
        string $tableName,
        string $teamForeignKey,
        string $pivotKey,
        string $modelMorphKey,
        string $primaryKeyName,
        string $indexName,
    ): void {
        Schema::table($tableName, function (Blueprint $table) use ($primaryKeyName, $indexName): void {
            $table->dropPrimary($primaryKeyName);
            $table->dropIndex($indexName);
        });

        Schema::table($tableName, function (Blueprint $table) use ($teamForeignKey): void {
            $table->unsignedBigInteger($teamForeignKey)->change();
        });

        Schema::table($tableName, function (Blueprint $table) use ($teamForeignKey, $pivotKey, $modelMorphKey, $primaryKeyName, $indexName): void {
            $table->index($teamForeignKey, $indexName);
            $table->primary([$teamForeignKey, $pivotKey, $modelMorphKey, 'model_type'], $primaryKeyName);
        });
    }
};
