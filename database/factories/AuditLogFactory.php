<?php

namespace Database\Factories;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<AuditLog>
 */
class AuditLogFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'actor_id' => null,
            'event' => 'batch.updated',
            'subject_type' => null,
            'subject_id' => null,
            'subject_label' => fake()->words(2, true),
            'trace_id' => (string) Str::uuid(),
            'before_json' => null,
            'after_json' => null,
            'reason' => null,
            'occurred_at' => now(),
        ];
    }
}
