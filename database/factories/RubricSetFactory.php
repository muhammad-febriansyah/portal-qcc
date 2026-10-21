<?php

namespace Database\Factories;

use App\Models\RubricSet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RubricSet>
 */
class RubricSetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'scoring_type' => 'proposal',
            'name' => 'Rubrik Draft Proposal',
        ];
    }
}
