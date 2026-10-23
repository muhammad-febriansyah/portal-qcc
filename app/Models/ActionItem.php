<?php

namespace App\Models;

use App\Enums\ActionStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property ActionStatus $status
 * @property Carbon|null $due_date
 * @property Carbon|null $completed_at
 */
class ActionItem extends Model
{
    protected $table = 'action_items';

    /** @var list<string> */
    protected $fillable = ['project_id', 'source_type', 'source_id', 'title', 'description', 'pic_id', 'due_date', 'status', 'completed_at', 'created_by'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ActionStatus::class,
            'due_date' => 'date:Y-m-d',
            'completed_at' => 'datetime',
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
