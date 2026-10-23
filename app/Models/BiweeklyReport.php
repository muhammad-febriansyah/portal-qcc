<?php

namespace App\Models;

use App\Enums\ReportStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * @property int $period_no
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 * @property Carbon|null $meeting_date
 * @property string|null $agenda
 * @property string|null $summary
 * @property string|null $planned
 * @property string|null $actual
 * @property string|null $issues
 * @property string|null $decisions
 * @property ReportStatus $status
 * @property Carbon|null $submitted_at
 * @property Carbon|null $reviewed_at
 */
class BiweeklyReport extends Model
{
    protected $table = 'biweekly_reports';

    /** @var list<string> */
    protected $fillable = ['project_id', 'period_no', 'starts_on', 'ends_on', 'meeting_date', 'agenda', 'summary', 'planned', 'actual', 'issues', 'decisions', 'status', 'created_by'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ReportStatus::class,
            'starts_on' => 'date:Y-m-d',
            'ends_on' => 'date:Y-m-d',
            'meeting_date' => 'date:Y-m-d',
            'submitted_at' => 'datetime',
            'reviewed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsToMany<User, $this> */
    public function attendees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'biweekly_attendees', 'biweekly_report_id', 'user_id');
    }

    /** @return BelongsTo<User, $this> */
    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    /** @return MorphMany<EvidenceLink, $this> */
    public function evidenceLinks(): MorphMany
    {
        return $this->morphMany(EvidenceLink::class, 'target');
    }

    /** @return HasMany<ActionItem, $this> */
    public function actions(): HasMany
    {
        return $this->hasMany(ActionItem::class, 'source_id')->where('source_type', 'BIWEEKLY_REPORT');
    }
}
