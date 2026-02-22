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
        $this->call(RolesAndPermissionsSeeder::class);

        $tenant = Tenant::create([
            'id' => '1',
            'name' => 'Demo Law Firm',
            'slug' => 'demo-law-firm',
        ]);

        setPermissionsTeamId($tenant->id);

        User::factory()->create([
            'name' => 'Super Admin',
            'email' => 'super@demo.test',
        ])->assignRole('super-admin');

        User::factory()->forTenant($tenant)->create([
            'name' => 'Tenant Admin',
            'email' => 'admin@demo.test',
        ])->assignRole('tenant-admin');

        User::factory()->forTenant($tenant)->create([
            'name' => 'Partner User',
            'email' => 'partner@demo.test',
        ])->assignRole('partner');

        User::factory()->forTenant($tenant)->create([
            'name' => 'Associate User',
            'email' => 'associate@demo.test',
        ])->assignRole('associate');

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
    }
}
