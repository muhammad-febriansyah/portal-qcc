<?php

namespace App\Models;

use App\Enums\QccStep;
use App\Enums\StepStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

/**
 * One of the eight QCC steps of a project (table qcc_steps).
 *
 * @property int $step_number
 * @property StepStatus $status
 * @property int $progress_pct
 * @property int $lock_version
 * @property Carbon|null $target_date
 * @property Carbon|null $submitted_at
 * @property Carbon|null $approved_at
 * @property Carbon|string|null $updated_at
 */
class ProjectStep extends Model
{
    protected $table = 'qcc_steps';

    /** @var list<string> */
    protected $fillable = ['project_id', 'step_number'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'step_number' => 'integer',
            'status' => StepStatus::class,
            'progress_pct' => 'integer',
            'lock_version' => 'integer',
            'target_date' => 'date:Y-m-d',
            'submitted_at' => 'datetime',
            'approved_at' => 'datetime',
        ];
    }

    public function definition(): QccStep
    {
        return QccStep::from($this->step_number);
    }

    /** @return HasMany<ProjectStepHistory, $this> */
    public function histories(): HasMany
    {
        return $this->hasMany(ProjectStepHistory::class, 'qcc_step_id')->orderByDesc('id');
    }

    /** @return MorphMany<EvidenceLink, $this> */
    public function evidenceLinks(): MorphMany
    {
        return $this->morphMany(EvidenceLink::class, 'target');
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
