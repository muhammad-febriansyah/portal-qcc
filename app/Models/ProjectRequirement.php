<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One competency a project asks for. The minimum level is optional.
 */
class ProjectRequirement extends Model
{
    /** @var list<string> */
    protected $fillable = ['project_id', 'competency_id', 'minimum_level'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'minimum_level' => 'integer',
            'priority' => 'integer',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<Competency, $this> */
    public function competency(): BelongsTo
    {
        return $this->belongsTo(Competency::class);
    }
}
