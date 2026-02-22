<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Matter;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        setPermissionsTeamId(null);
        $this->call(RolesAndPermissionsSeeder::class);

        $tenant = Tenant::create([
            'id' => '1',
            'name' => 'Demo Law Firm',
            'slug' => 'demo-law-firm',
        ]);

        $superAdmin = User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'super@demo.test',
        ]);

        $this->assignRoleWithTeamContext($superAdmin, 'super-admin', $tenant->id);

        $tenantAdmin = User::factory()->forTenant($tenant)->create([
            'name' => 'Tenant Admin',
            'email' => 'admin@demo.test',
        ]);
        $this->assignRoleWithTeamContext($tenantAdmin, 'tenant-admin', $tenant->id);

        $partner = User::factory()->forTenant($tenant)->create([
            'name' => 'Partner User',
            'email' => 'partner@demo.test',
        ]);
        $this->assignRoleWithTeamContext($partner, 'partner', $tenant->id);

        $associate = User::factory()->forTenant($tenant)->create([
            'name' => 'Associate User',
            'email' => 'associate@demo.test',
        ]);
        $this->assignRoleWithTeamContext($associate, 'associate', $tenant->id);

        tenancy()->initialize($tenant);

        Client::factory()
            ->count(5)
            ->create(['tenant_id' => $tenant->id])
            ->each(function (Client $client) use ($tenant): void {
                Matter::factory()
                    ->count(3)
                    ->create([
                        'tenant_id' => $tenant->id,
                        'client_id' => $client->id,
                    ]);
            });

        tenancy()->end();
        setPermissionsTeamId(null);
    }

    protected function assignRoleWithTeamContext(User $user, string $roleName, ?string $teamId): void
    {
        setPermissionsTeamId($teamId);
        $user->assignRole($roleName);
    }
}
