<?php

namespace App\Models;

use Database\Factories\CompetencyGroupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CompetencyGroup extends Model
{
    /** @use HasFactory<CompetencyGroupFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'name',
    ];

    public function competencies(): HasMany
    {
        return $this->hasMany(Competency::class, 'group_id');
    }
}
