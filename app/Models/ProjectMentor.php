<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A mentor assigned to a project. Ending an assignment keeps the row so earlier
 * mentoring reports stay attributable.
 *
 * @property CarbonImmutable|string $assigned_at
 * @property CarbonImmutable|string|null $ended_at
 */
class ProjectMentor extends Model
{
    protected $table = 'project_mentors';

    /** @var list<string> */
    protected $fillable = ['project_id', 'mentor_id', 'assigned_by', 'status', 'assigned_at', 'ended_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['assigned_at' => 'datetime', 'ended_at' => 'datetime'];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function mentor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mentor_id');
    }
}
