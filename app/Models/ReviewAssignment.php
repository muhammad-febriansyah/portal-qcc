<?php

namespace App\Models;

use App\Concerns\Auditable;
use Database\Factories\ReviewAssignmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $project_id
 * @property int $reviewer_id
 * @property string $scope
 * @property int|null $granted_by
 * @property Carbon|null $expires_at
 * @property string $status
 */
class ReviewAssignment extends Model
{
    /** @use HasFactory<ReviewAssignmentFactory> */
    use Auditable, HasFactory;

    public const SCOPES = [
        'proposal' => 'Proposal',
        'steps' => '8 Step QCC',
        'biweekly' => 'Bi-weekly Report',
        'scoring' => 'AI Scoring',
    ];

    public const ACTIVE = 'ACTIVE';

    public const REVOKED = 'REVOKED';

    protected $fillable = [
        'project_id',
        'reviewer_id',
        'scope',
        'granted_by',
        'expires_at',
        'status',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    /** @return BelongsTo<User, $this> */
    public function granter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'granted_by');
    }

    /** @param Builder<ReviewAssignment> $query
     * @return Builder<ReviewAssignment>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', self::ACTIVE)
            ->where(fn (Builder $active): Builder => $active->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }

    public function auditEventPrefix(): string
    {
        return 'review_assignment';
    }

    public function auditLabel(): string
    {
        return sprintf('%s · %s', $this->project->title, $this->reviewer->name);
    }

    /** @param array<string, mixed> $changes */
    public function auditUpdatedEvent(array $changes): ?string
    {
        return ($changes['status'] ?? null) === self::REVOKED ? 'review_assignment.revoked' : null;
    }
}
