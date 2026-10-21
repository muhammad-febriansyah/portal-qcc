<?php

namespace App\Models;

use App\Enums\TeamMatchingStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One AI team suggestion for a project. Never a team by itself: the suggestion
 * only becomes members after a person confirms it.
 */
class TeamMatchingRun extends Model
{
    /** @var list<string> */
    protected $fillable = ['project_id', 'requested_by', 'status', 'idempotency_key', 'candidate_snapshot_json'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TeamMatchingStatus::class,
            'candidate_snapshot_json' => 'array',
            'output_json' => 'array',
            'token_usage_json' => 'array',
            'validated_at' => 'datetime',
            'confirmed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
