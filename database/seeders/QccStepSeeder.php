<?php

namespace Database\Seeders;

use App\Actions\Proposals\InitializeProjectSteps;
use App\Enums\ProposalStatus;
use App\Models\Project;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Seeder;

class QccStepSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(InitializeProjectSteps $initializeProjectSteps): void
    {
        Project::query()
            ->where('proposal_status', ProposalStatus::Approved->value)
            ->chunkById(100, function (Collection $projects) use ($initializeProjectSteps): void {
                foreach ($projects as $project) {
                    $initializeProjectSteps->handle($project);
                }
            });
    }
}
