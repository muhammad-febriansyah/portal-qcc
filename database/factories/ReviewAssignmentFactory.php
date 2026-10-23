<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\ReviewAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ReviewAssignment>
 */
class ReviewAssignmentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'reviewer_id' => User::factory(),
            'scope' => 'proposal',
            'granted_by' => User::factory(),
            'expires_at' => null,
            'status' => ReviewAssignment::ACTIVE,
        ];
    }
}
