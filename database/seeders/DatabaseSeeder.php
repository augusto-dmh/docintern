<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\Client;
use App\Models\Document;
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
        $documentUploaders = collect([$tenantAdmin, $partner, $associate]);

        Client::factory()
            ->count(5)
            ->create(['tenant_id' => $tenant->id])
            ->each(function (Client $client) use ($tenant, $documentUploaders): void {
                Matter::factory()
                    ->count(3)
                    ->create([
                        'tenant_id' => $tenant->id,
                        'client_id' => $client->id,
                    ])
                    ->each(function (Matter $matter, int $matterIndex) use ($tenant, $documentUploaders): void {
                        $documentCount = match ($matterIndex % 3) {
                            0 => 0,
                            1 => 2,
                            default => 3,
                        };

                        for ($documentIndex = 0; $documentIndex < $documentCount; $documentIndex++) {
                            $status = match ($documentIndex % 3) {
                                0 => 'uploaded',
                                1 => 'ready_for_review',
                                default => 'approved',
                            };

                            /** @var User $uploader */
                            $uploader = $documentUploaders->random();
                            $createdAt = now()->subDays(($matterIndex * 2) + $documentIndex + 1);

                            $document = Document::factory()->create([
                                'tenant_id' => $tenant->id,
                                'matter_id' => $matter->id,
                                'uploaded_by' => $uploader->id,
                                'title' => sprintf('%s Exhibit %d', $matter->title, $documentIndex + 1),
                                'file_path' => sprintf(
                                    'tenants/%s/documents/demo-%d-%d.pdf',
                                    $tenant->id,
                                    $matter->id,
                                    $documentIndex + 1,
                                ),
                                'file_name' => sprintf('matter-%d-file-%d.pdf', $matter->id, $documentIndex + 1),
                                'status' => $status,
                                'created_at' => $createdAt,
                                'updated_at' => $createdAt->copy()->addHour(),
                            ]);

                            AuditLog::factory()->create([
                                'tenant_id' => $tenant->id,
                                'user_id' => $uploader->id,
                                'auditable_type' => Document::class,
                                'auditable_id' => $document->id,
                                'action' => 'uploaded',
                                'metadata' => [
                                    'ip_address' => fake()->ipv4(),
                                    'user_agent' => 'demo-seeder/upload',
                                ],
                                'created_at' => $createdAt,
                                'updated_at' => $createdAt,
                            ]);

                            $viewedAt = $createdAt->copy()->addHours(2);
                            AuditLog::factory()->create([
                                'tenant_id' => $tenant->id,
                                'user_id' => $uploader->id,
                                'auditable_type' => Document::class,
                                'auditable_id' => $document->id,
                                'action' => 'viewed',
                                'metadata' => [
                                    'ip_address' => fake()->ipv4(),
                                    'user_agent' => 'demo-seeder/view',
                                ],
                                'created_at' => $viewedAt,
                                'updated_at' => $viewedAt,
                            ]);

                            if ($status === 'approved') {
                                $downloadedAt = $createdAt->copy()->addHours(4);
                                AuditLog::factory()->create([
                                    'tenant_id' => $tenant->id,
                                    'user_id' => $uploader->id,
                                    'auditable_type' => Document::class,
                                    'auditable_id' => $document->id,
                                    'action' => 'downloaded',
                                    'metadata' => [
                                        'ip_address' => fake()->ipv4(),
                                        'user_agent' => 'demo-seeder/download',
                                    ],
                                    'created_at' => $downloadedAt,
                                    'updated_at' => $downloadedAt,
                                ]);
                            }
                        }
                    });
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
