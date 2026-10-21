<?php

namespace Database\Factories;

use App\Enums\BatchStatus;
use App\Models\QccBatch;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QccBatch>
 */
class QccBatchFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startsOn = fake()->dateTimeBetween('-6 months', '+6 months');

        return [
            'code' => 'BATCH-'.fake()->unique()->numerify('####'),
            'name' => 'Batch '.fake()->unique()->words(2, true),
            'starts_on' => $startsOn->format('Y-m-d'),
            'ends_on' => (clone $startsOn)->modify('+3 months')->format('Y-m-d'),
            'status' => BatchStatus::Draft,
        ];
    }

    public function active(): static
    {
        return $this->state(['status' => BatchStatus::Active]);
    }

    public function closed(): static
    {
        return $this->state(['status' => BatchStatus::Closed]);
    }
}
