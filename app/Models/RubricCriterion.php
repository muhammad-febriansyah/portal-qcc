<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RubricCriterion extends Model
{
    /** @var list<string> */
    protected $fillable = [
        'rubric_version_id',
        'code',
        'name',
        'description',
        'weight_pct',
        'position',
    ];

    /** @return BelongsTo<RubricVersion, $this> */
    public function rubricVersion(): BelongsTo
    {
        return $this->belongsTo(RubricVersion::class);
    }
}
