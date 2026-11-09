<?php

namespace Database\Factories;

use App\Models\Competency;
use App\Models\MemberCompetency;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MemberCompetency>
 */
class MemberCompetencyFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'competency_id' => Competency::factory(),
            'self_level' => fake()->numberBetween(1, 4),
        ];
    }
}
