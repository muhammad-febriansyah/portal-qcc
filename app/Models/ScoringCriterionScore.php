<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScoringCriterionScore extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'scoring_result_id',
        'rubric_criterion_id',
        'ai_score',
        'rationale',
        'gaps_json',
        'uncertainty',
        'evidence_references_json',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ai_score' => 'decimal:2',
            'reviewer_score' => 'decimal:2',
            'final_score' => 'decimal:2',
            'gaps_json' => 'array',
            'evidence_references_json' => 'array',
        ];
    }

    /** @return BelongsTo<RubricCriterion, $this> */
    public function criterion(): BelongsTo
    {
        return $this->belongsTo(RubricCriterion::class, 'rubric_criterion_id');
    }
}
