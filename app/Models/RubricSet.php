<?php

namespace App\Models;

use Database\Factories\RubricSetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RubricSet extends Model
{
    /** @use HasFactory<RubricSetFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = ['scoring_type', 'name'];

    /** @return HasMany<RubricVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(RubricVersion::class);
    }
}
