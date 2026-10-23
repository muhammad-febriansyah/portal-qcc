<?php

namespace App\Models;

use App\Enums\ReportStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|string $session_at
 * @property ReportStatus $status
 * @property Carbon|string|null $submitted_at
 * @property Carbon|string|null $acknowledged_at
 * @property string $topic
 * @property string|null $findings
 * @property string|null $suggestions
 * @property string|null $next_steps
 * @property string|null $team_response
 */
class MentoringReport extends Model
{
    protected $table = 'mentoring_reports';

    /** @var list<string> */
    protected $fillable = ['project_id', 'project_mentor_id', 'session_at', 'topic', 'findings', 'suggestions', 'next_steps', 'status', 'created_by'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ReportStatus::class,
            'session_at' => 'datetime',
            'submitted_at' => 'datetime',
            'acknowledged_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<ProjectMentor, $this> */
    public function assignment(): BelongsTo
    {
        return $this->belongsTo(ProjectMentor::class, 'project_mentor_id');
    }

    /** @return HasMany<ActionItem, $this> */
    public function actions(): HasMany
    {
        return $this->hasMany(ActionItem::class, 'source_id')->where('source_type', 'MENTORING_REPORT');
    }

    /** @return MorphMany<EvidenceLink, $this> */
    public function evidenceLinks(): MorphMany
    {
        return $this->morphMany(EvidenceLink::class, 'target');
    }
}
