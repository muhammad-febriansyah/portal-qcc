<?php

namespace Database\Factories;

use App\Models\CompetencyGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompetencyGroup>
 */
class CompetencyGroupFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->lexify('G????'),
            'name' => fake()->words(2, true),
        ];
    }
}
