<?php

namespace App\Services\Teams\Matching;

use App\Models\Project;
use Illuminate\Support\Facades\DB;

class TeamMatchingInputFingerprint
{
    public function for(Project $project): string
    {
        $current = Project::query()->findOrFail($project->id);
        $requirements = DB::table('project_requirements')
            ->join('competency_master', 'competency_master.id', '=', 'project_requirements.competency_id')
            ->where('project_requirements.project_id', $project->id)
            ->orderBy('competency_master.code')
            ->get(['competency_master.code', 'project_requirements.minimum_level'])
            ->map(fn (object $requirement): array => [
                'code' => (string) $requirement->code,
                'minimum_level' => $requirement->minimum_level === null ? null : (int) $requirement->minimum_level,
            ])->all();

        $input = [
            'title' => $current->title,
            'theme' => $current->theme,
            'background' => $current->background,
            'problem_statement' => $current->problem_statement,
            'current_situation' => $current->current_situation,
            'baseline' => $current->baseline,
            'target' => $current->target,
            'scope' => $current->scope,
            'learning_goals' => $current->learning_goals_json,
            'root_cause_analysis' => $current->rca,
            'improvement_plan' => $current->improvement_plan,
            'business_benefit' => $current->business_benefit,
            'supplementary_information' => $current->supplementary_information,
            'requirements' => $requirements,
        ];

        return hash('sha256', json_encode($input, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
    }

    public function matches(Project $project, mixed $fingerprint): bool
    {
        return is_string($fingerprint)
            && $fingerprint !== ''
            && hash_equals($fingerprint, $this->for($project));
    }
}
