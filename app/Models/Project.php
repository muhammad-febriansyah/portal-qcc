<?php

namespace App\Models;

use App\Enums\ProposalStatus;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * @property ProposalStatus $proposal_status
 * @property int $lock_version
 * @property Carbon|null $submitted_at
 * @property Carbon|null $approved_at
 * @property array<int, string>|null $learning_goals_json
 * @property int $steps_total
 * @property int $steps_completed
 * @property int $approved_steps_count
 * @property float|int|string|null $steps_avg_progress_pct
 */
class Project extends Model
{
    /** @use HasFactory<ProjectFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'proposal_status' => ProposalStatus::class,
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
            'learning_goals_json' => 'array',
        ];
    }

    /** @return BelongsTo<QccBatch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(QccBatch::class, 'batch_id');
    }

    /** @return BelongsTo<User, $this> */
    public function proposer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'proposer_id');
    }

    /** @return HasMany<ProjectAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(ProjectAttachment::class);
    }

    /** @return HasMany<ProjectEvidence, $this> */
    public function evidence(): HasMany
    {
        return $this->hasMany(ProjectEvidence::class);
    }

    /** @return HasMany<ProjectStep, $this> */
    public function steps(): HasMany
    {
        return $this->hasMany(ProjectStep::class)->orderBy('step_number');
    }

    /** @return HasMany<ProjectMentor, $this> */
    public function mentorAssignments(): HasMany
    {
        return $this->hasMany(ProjectMentor::class);
    }

    /** @return HasMany<ReviewAssignment, $this> */
    public function reviewAssignments(): HasMany
    {
        return $this->hasMany(ReviewAssignment::class);
    }

    /** @return HasMany<BiweeklyReport, $this> */
    public function biweeklyReports(): HasMany
    {
        return $this->hasMany(BiweeklyReport::class)->orderByDesc('period_no');
    }

    /** @return HasMany<MentoringReport, $this> */
    public function mentoringReports(): HasMany
    {
        return $this->hasMany(MentoringReport::class)->orderByDesc('session_at');
    }

    /** @return HasMany<ProjectRisk, $this> */
    public function risks(): HasMany
    {
        return $this->hasMany(ProjectRisk::class);
    }

    /** @return HasMany<ActionItem, $this> */
    public function actionItems(): HasMany
    {
        return $this->hasMany(ActionItem::class);
    }

    /** @return HasMany<ProjectRequirement, $this> */
    public function requirements(): HasMany
    {
        return $this->hasMany(ProjectRequirement::class);
    }

    /** @return HasMany<ReviewDecision, $this> */
    public function reviewDecisions(): HasMany
    {
        return $this->hasMany(ReviewDecision::class)->latest('decided_at');
    }

    /** @return BelongsToMany<User, $this> */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_members')
            ->withPivot(['role_in_project', 'status', 'joined_at', 'left_at'])
            ->withTimestamps();
    }

    /**
     * Restrict project queries to records the user may view.
     * A mentor sees only the projects they are actively assigned to.
     *
     * @param  Builder<Project>  $query
     * @return Builder<Project>
     */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->can('projects.viewAny')) {
            return $query;
        }

        if (! $user->can('projects.view')) {
            return $query->whereRaw('1 = 0');
        }

        $projectRoles = [];

        if ($user->hasRole('Team Leader')) {
            $projectRoles[] = 'LEADER';
        }

        if ($user->hasRole('SIMer / Member')) {
            $projectRoles[] = 'MEMBER';
        }

        $isMentor = $user->hasRole('Mentor');

        return $query->where(function (Builder $query) use ($user, $projectRoles, $isMentor): void {
            $query->where(function (Builder $members) use ($user, $projectRoles): void {
                $members->whereHas('members', function (Builder $members) use ($user, $projectRoles): void {
                    $members
                        ->where('users.id', $user->id)
                        ->where('project_members.status', 'ACTIVE')
                        ->whereNull('project_members.left_at')
                        ->whereIn('project_members.role_in_project', $projectRoles);
                });
            })->orWhere(function (Builder $proposals) use ($user): void {
                $proposals->where('proposer_id', $user->id);
            })->when($isMentor, fn (Builder $q): Builder => $q->orWhereHas('mentorAssignments', fn (Builder $assignments): Builder => $assignments
                ->where('mentor_id', $user->id)->where('status', 'ACTIVE')))
                ->orWhereHas('reviewAssignments', fn (Builder $assignments): Builder => $assignments
                    ->active()->where('reviewer_id', $user->id));
        });
    }
}
