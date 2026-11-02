<?php

namespace App\Models;

use App\Concerns\Auditable;
use Database\Factories\QccBatchScheduleFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/** @property Carbon $scheduled_on */
#[Fillable(['batch_id', 'schedule_type', 'sequence_no', 'scheduled_on', 'created_by'])]
class QccBatchSchedule extends Model
{
    /** @use HasFactory<QccBatchScheduleFactory> */
    use Auditable, HasFactory;

    protected function casts(): array
    {
        return ['scheduled_on' => 'date:Y-m-d'];
    }

    /** @return BelongsTo<QccBatch, $this> */
    public function batch(): BelongsTo
    {
        return $this->belongsTo(QccBatch::class, 'batch_id');
    }

    public function auditEventPrefix(): string
    {
        return 'batch_schedule';
    }

    public function auditLabel(): string
    {
        return $this->schedule_type.' '.$this->sequence_no;
    }
}
