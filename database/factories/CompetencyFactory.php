<?php

namespace Database\Factories;

use App\Models\Competency;
use App\Models\CompetencyGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Competency>
 */
class CompetencyFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'group_id' => CompetencyGroup::factory(),
            'code' => sprintf('C%03d', fake()->unique()->numberBetween(1, 62)),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
