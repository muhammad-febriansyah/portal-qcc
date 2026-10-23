<?php

namespace App\Models;

use App\Enums\TeamMatchingStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One AI team suggestion for a project. Never a team by itself: the suggestion
 * only becomes members after a person confirms it.
 *
 * @property TeamMatchingStatus $status
 * @property array{
 *     project_id: int, generated_at: string,
 *     requirements: list<array{competency_id: int, minimum_level: int|null}>,
 *     candidates: list<array{
 *         candidate_user_id: int, organization_id: int, availability_status: string|null,
 *         competencies: list<array{competency_id: int, self_level: int, verified_level: int|null}>,
 *         qcc_experience: array{projects_count: int, leader_projects_count: int, completed_projects_count: int}
 *     }>
 * }|null $candidate_snapshot_json
 * @property array{
 *     recommended_members?: list<array{
 *         user_id: int, proposed_role: string, matched_competency_ids: list<int>, match_reason: string,
 *         contribution: string, gaps: list<string>, uncertainties: list<string>
 *     }>,
 *     team_gaps?: list<string>, cautions?: list<string>,
 *     mitigations?: list<array{gap: string, recommendation: string, type: string}>, rule_problems?: list<string>
 * }|null $output_json
 * @property Carbon|null $confirmed_at
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
