<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ScoringResult extends Model
{
    /** @var list<string> */
    protected $fillable = ['scoring_run_id', 'overall_summary', 'cautions_json', 'ai_weighted_score'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cautions_json' => 'array',
            'ai_weighted_score' => 'decimal:2',
            'reviewer_weighted_score' => 'decimal:2',
            'final_score' => 'decimal:2',
            'reviewed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ScoringRun, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(ScoringRun::class, 'scoring_run_id');
    }

    /** @return HasMany<ScoringCriterionScore, $this> */
    public function criterionScores(): HasMany
    {
        return $this->hasMany(ScoringCriterionScore::class);
    }
}
