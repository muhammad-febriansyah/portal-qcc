<?php

namespace App\Models;

use App\Enums\RiskLevel;
use App\Enums\RiskStatus;
use App\Enums\RiskType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property RiskType $type
 * @property RiskLevel $likelihood
 * @property RiskLevel $priority
 * @property RiskStatus $status
 * @property Carbon|null $due_date
 * @property Carbon|null $resolved_at
 */
class ProjectRisk extends Model
{
    protected $table = 'project_risks';

    /** @var list<string> */
    protected $fillable = ['project_id', 'type', 'title', 'description', 'impact', 'likelihood', 'priority', 'mitigation', 'pic_id', 'due_date', 'status', 'resolved_at', 'created_by'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => RiskType::class,
            'likelihood' => RiskLevel::class,
            'priority' => RiskLevel::class,
            'status' => RiskStatus::class,
            'due_date' => 'date:Y-m-d',
            'resolved_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Project, $this> */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /** @return BelongsTo<User, $this> */
    public function pic(): BelongsTo
    {
        return $this->belongsTo(User::class, 'pic_id');
    }
}
