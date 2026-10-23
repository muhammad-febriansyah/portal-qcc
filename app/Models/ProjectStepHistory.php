<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Append-only trail of a step: every save and every workflow move, with the
 * content as it was at that moment.
 *
 * @property Carbon|string $created_at
 */
class ProjectStepHistory extends Model
{
    protected $table = 'qcc_step_histories';

    public $timestamps = false;

    /** @var list<string> */
    protected $fillable = ['qcc_step_id', 'actor_id', 'action', 'previous_status', 'new_status', 'version', 'snapshot_json', 'reason', 'created_at'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['snapshot_json' => 'array', 'created_at' => 'datetime', 'version' => 'integer'];
    }

    /** @return BelongsTo<ProjectStep, $this> */
    public function step(): BelongsTo
    {
        return $this->belongsTo(ProjectStep::class, 'qcc_step_id');
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
