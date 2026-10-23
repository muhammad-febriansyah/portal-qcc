<?php

namespace App\Services;

use App\Models\Project;
use App\Models\ReviewAssignment;
use App\Models\TeamMatchingRun;
use App\Models\User;
use App\Notifications\ProjectActivityNotification;
use App\Notifications\ProposalWorkflowNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;

/**
 * Sends the in-app notices of the project flows after the database transaction
 * commits, to the right people only, and never to the person who acted.
 */
class ProjectNotifier
{
    /**
     * @param  iterable<User>  $recipients
     */
    public function notify(iterable $recipients, Project $project, string $title, string $message, string $url, string $kind, ?User $actor = null): void
    {
        if (! ProjectActivityNotification::enabled()) {
            return;
        }

        $users = collect($recipients)->unique('id')->reject(fn (User $user): bool => $actor !== null && $user->id === $actor->id)->values();

        if ($users->isEmpty()) {
            return;
        }

        DB::afterCommit(function () use ($users, $project, $title, $message, $url, $kind): void {
            $users->each(fn (User $user) => $user->notify(new ProjectActivityNotification($project->id, $title, $message, $url, $kind)));
        });
    }

    public function notifyTeamCompositionPending(Project $project, User $actor): void
    {
        if (! ProjectActivityNotification::enabled()
            || ! $actor->hasRole('SIMer / Member')
            || $actor->hasRole('Team Leader')) {
            return;
        }

        DB::transaction(function () use ($project, $actor): void {
            $lockedProject = Project::query()->lockForUpdate()->findOrFail($project->id);

            if ($lockedProject->proposal_status->value !== 'DRAFT' || $lockedProject->proposer_id !== $actor->id) {
                return;
            }

            $requiredFields = [
                'title', 'theme', 'background', 'problem_statement', 'current_situation',
                'baseline', 'target', 'scope', 'rca', 'improvement_plan', 'business_benefit',
            ];

            foreach ($requiredFields as $field) {
                if (blank($lockedProject->{$field})) {
                    return;
                }
            }

            if (count(array_filter($lockedProject->learning_goals_json ?? [], fn ($goal): bool => filled($goal))) !== 3) {
                return;
            }

            if (TeamMatchingRun::query()->where('project_id', $lockedProject->id)->whereNotNull('confirmed_at')->exists()) {
                return;
            }

            $noticeExists = DB::table('notifications')
                ->where('type', ProposalWorkflowNotification::class)
                ->where('data->project_id', $lockedProject->id)
                ->where('data->decision', 'TEAM_COMPOSITION_PENDING')
                ->exists();

            if ($noticeExists) {
                return;
            }

            User::query()->where('is_active', true)->get()
                ->filter(fn (User $user): bool => $user->can('projects.viewAny') && $user->can('projects.review'))
                ->reject(fn (User $user): bool => $user->id === $actor->id)
                ->each(fn (User $user) => $user->notify(new ProposalWorkflowNotification(
                    $lockedProject->id,
                    'Proposal menunggu penyusunan tim',
                    $lockedProject->title.' menunggu tim disusun oleh Owner QCC.',
                    route('proposals.candidates', $lockedProject, false),
                    'TEAM_COMPOSITION_PENDING',
                )));
        });
    }

    /** @return Collection<int, User> */
    public function team(Project $project): Collection
    {
        return $project->members()->wherePivot('status', 'ACTIVE')->wherePivotNull('left_at')->where('users.is_active', true)->get();
    }

    /** @return Collection<int, User> */
    public function leaders(Project $project): Collection
    {
        return $project->members()->wherePivot('status', 'ACTIVE')->wherePivotNull('left_at')->wherePivot('role_in_project', 'LEADER')->where('users.is_active', true)->get();
    }

    /** @return Collection<int, User> */
    public function reviewers(Project $project, string $scope): Collection
    {
        if (! in_array($scope, ['proposal', 'steps', 'biweekly'], true)) {
            return collect();
        }

        $reviewers = User::query()
            ->where('is_active', true)
            ->where(function ($query): void {
                $query->whereHas('permissions', fn ($permissions) => $permissions->whereIn('name', ['projects.review', 'projects.approve']))
                    ->orWhereHas('roles.permissions', fn ($permissions) => $permissions->whereIn('name', ['projects.review', 'projects.approve']));
            })
            ->where(function ($query) use ($project, $scope): void {
                $query->where(function ($query): void {
                    $query->permission('projects.viewAny');
                })->orWhereHas('reviewAssignments', fn ($assignments) => $assignments
                    ->active()
                    ->where('project_id', $project->id)
                    ->where('scope', $scope));
            })
            ->when($scope === 'proposal', fn ($query) => $query->whereDoesntHave('roles', fn ($roles) => $roles->where('name', 'Mentor')))
            ->get();

        if ($scope === 'proposal') {
            return $reviewers;
        }

        $mentors = $scope === 'biweekly'
            ? $this->mentors($project)
            : $this->mentors($project)->filter(fn (User $mentor): bool => $mentor->can('projects.review') || $mentor->can('projects.approve'));

        return $reviewers->merge($mentors)
            ->unique('id')->values();
    }

    /** @return Collection<int, User> */
    public function scoringReviewers(Project $project): Collection
    {
        if (! Permission::query()->where('name', 'scoring.view')->where('guard_name', 'web')->exists()) {
            return collect();
        }

        return User::permission('scoring.view')->where('is_active', true)->get()
            ->filter(fn (User $user): bool => $user->can('projects.viewAny') || ReviewAssignment::query()->active()
                ->where('project_id', $project->id)
                ->where('reviewer_id', $user->id)
                ->where('scope', 'scoring')
                ->exists())
            ->values();
    }

    /** @return Collection<int, User> */
    public function mentors(Project $project): Collection
    {
        return User::query()->whereIn('id', $project->mentorAssignments()->where('status', 'ACTIVE')->pluck('mentor_id'))->where('is_active', true)->get();
    }
}
