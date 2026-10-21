<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RubricVersion extends Model
{
    /** @var list<string> */
    protected $fillable = ['rubric_set_id', 'version_number', 'publication_status'];

    /** @return BelongsTo<RubricSet, $this> */
    public function rubricSet(): BelongsTo
    {
        return $this->belongsTo(RubricSet::class);
    }

    /** @return HasMany<RubricCriterion, $this> */
    public function criteria(): HasMany
    {
        return $this->hasMany(RubricCriterion::class)->orderBy('position');
    }
}
