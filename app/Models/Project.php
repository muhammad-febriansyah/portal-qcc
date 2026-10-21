<?php

namespace App\Models;

use App\Enums\ProposalStatus;
use Database\Factories\ProjectFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

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

    /** @return BelongsToMany<User, $this> */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_members')
            ->withPivot(['role_in_project', 'status', 'joined_at', 'left_at'])
            ->withTimestamps();
    }

    /**
     * Restrict project queries to records the user may view.
     * Mentor assignments remain closed until project_mentors exists.
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

        if ($projectRoles === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $query) use ($user, $projectRoles): void {
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
            });
        });
    }
}
