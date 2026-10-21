<?php

namespace Database\Factories;

use App\Enums\ProposalStatus;
use App\Models\Project;
use App\Models\QccBatch;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Project>
 */
class ProjectFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'batch_id' => QccBatch::factory(),
            'proposer_id' => User::factory(),
            'title' => fake()->unique()->sentence(4),
            'proposal_status' => ProposalStatus::Draft,
        ];
    }

    public function status(ProposalStatus $status): static
    {
        return $this->state(['proposal_status' => $status]);
    }
}
