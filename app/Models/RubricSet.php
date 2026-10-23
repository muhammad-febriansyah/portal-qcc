<?php

namespace App\Models;

use App\Concerns\Auditable;
use Database\Factories\RubricSetFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $name
 * @property string $scoring_type
 */
class RubricSet extends Model
{
    /** @use HasFactory<RubricSetFactory> */
    use Auditable, HasFactory;

    /** @var list<string> */
    protected $fillable = ['scoring_type', 'name'];

    /** @return HasMany<RubricVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(RubricVersion::class);
    }

    public function auditEventPrefix(): string
    {
        return 'rubric';
    }

    public function auditLabel(): string
    {
        return $this->name;
    }
}
