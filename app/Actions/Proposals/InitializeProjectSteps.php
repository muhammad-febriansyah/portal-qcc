<?php

namespace App\Actions\Proposals;

use App\Enums\QccStep;
use App\Enums\StepStatus;
use App\Models\Project;
use Illuminate\Support\Facades\DB;

/**
 * Create the eight QCC steps of an approved project, due on the dates planned in the proposal
 * timeline. Safe to run again: steps that already exist are left as they are, so a retry never
 * duplicates or resets.
 */
class InitializeProjectSteps
{
    public function handle(Project $project): void
    {
        $now = now();
        $plannedDates = collect($project->step_timeline_json ?? [])->pluck('target_date', 'step_number');

        DB::table('qcc_steps')->insertOrIgnore(array_map(fn (QccStep $step): array => [
            'project_id' => $project->id,
            'step_number' => $step->value,
            'target_date' => $plannedDates->get($step->value),
            'status' => StepStatus::NotStarted->value,
            'progress_pct' => 0,
            'lock_version' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ], QccStep::cases()));
    }
}
