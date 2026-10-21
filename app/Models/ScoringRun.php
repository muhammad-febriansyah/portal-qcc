<?php

namespace App\Models;

use App\Enums\ScoringStatus;
use Database\Factories\ScoringRunFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

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
            'usage_json' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<RubricVersion, $this> */
    public function rubricVersion(): BelongsTo
    {
        return $this->belongsTo(RubricVersion::class);
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
