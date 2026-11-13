<?php

namespace Database\Factories;

use App\Models\Competency;
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
            'group_id' => null,
            'code' => fake()->randomElement(['H', 'S']).fake()->unique()->numberBetween(100, 999),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'is_active' => true,
        ];
    }
}
