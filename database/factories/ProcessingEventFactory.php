<?php

namespace Database\Factories;

use App\Models\Document;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\ProcessingEvent>
 */
class ProcessingEventFactory extends Factory
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
            'message_id' => (string) fake()->uuid(),
            'trace_id' => (string) fake()->uuid(),
            'event' => 'document.uploaded',
            'consumer_name' => 'upload-dispatch',
            'status_from' => null,
            'status_to' => 'uploaded',
            'metadata' => [
                'source' => 'factory',
            ],
        ];
    }
}
