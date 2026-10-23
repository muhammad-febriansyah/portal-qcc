<?php

namespace App\Models;

use App\Enums\ScoringStatus;
use Carbon\CarbonImmutable;
use Database\Factories\ScoringRunFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * @property ScoringStatus $status
 * @property list<array{evidence_id: int, title: string, sha256: string}>|array{evidence_count: int, minimum: int}|null $input_manifest_json
 * @property CarbonImmutable|string|null $created_at
 * @property CarbonImmutable|string|null $started_at
 * @property CarbonImmutable|string|null $completed_at
 * @property string|null $project_title
 * @property string|null $rubric_name
 * @property string|null $scoring_type
 * @property-read Project|null $project
 * @property-read RubricVersion|null $rubricVersion
 */
class ScoringRun extends Model
{
    /** @use HasFactory<ScoringRunFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'project_id',
        'rubric_version_id',
        'triggered_by',
        'status',
        'idempotency_key',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ScoringStatus::class,
            'input_manifest_json' => 'array',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
            'usage_json' => 'array',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<RubricVersion, $this> */
    public function rubricVersion(): BelongsTo
    {
        return $this->belongsTo(RubricVersion::class);
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    /** @return HasOne<ScoringResult, $this> */
    public function result(): HasOne
    {
        return $this->hasOne(ScoringResult::class);
    }
}
