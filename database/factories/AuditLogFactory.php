<?php

namespace Database\Factories;

use App\Models\Document;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\AuditLog>
 */
class AuditLogFactory extends Factory
{
    public function definition(): array
    {
        return [
            'tenant_id' => fn (array $attributes) => Document::find($attributes['auditable_id'])?->tenant_id ?? Tenant::factory()->create()->id,
            'user_id' => User::factory(),
            'auditable_type' => Document::class,
            'auditable_id' => Document::factory(),
            'action' => fake()->randomElement(['uploaded', 'viewed', 'downloaded', 'deleted']),
            'metadata' => [
                'ip_address' => fake()->ipv4(),
                'user_agent' => fake()->userAgent(),
            ],
        ];
    }
}
