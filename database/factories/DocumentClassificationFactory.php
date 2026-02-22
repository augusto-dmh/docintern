<?php

namespace Database\Factories;

use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DocumentClassification>
 */
class DocumentClassificationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'tenant_id' => fn (array $attributes) => Document::query()->find($attributes['document_id'])?->tenant_id,
            'document_id' => Document::factory(),
            'provider' => 'simulated',
            'type' => fake()->randomElement(['contract', 'tax', 'invoice', 'general']),
            'confidence' => fake()->randomFloat(4, 0.5, 0.9999),
            'metadata' => [
                'source' => 'factory',
            ],
        ];
    }
}
