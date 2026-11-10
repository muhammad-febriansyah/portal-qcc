<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\BatchStatus;
use Carbon\CarbonInterface;
use Database\Factories\QccBatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $starts_on
 * @property Carbon $ends_on
 * @property BatchStatus $status
 * @property int|null $participant_target
 * @property Carbon|null $registration_starts_on
 * @property Carbon|null $registration_ends_on
 * @property Carbon|null $selection_starts_on
 * @property Carbon|null $selection_ends_on
 * @property Carbon|null $execution_starts_on
 * @property Carbon|null $execution_ends_on
 */
#[Fillable([
    'code', 'name', 'starts_on', 'ends_on', 'status', 'participant_target',
    'registration_starts_on', 'registration_ends_on', 'selection_starts_on', 'selection_ends_on',
    'execution_starts_on', 'execution_ends_on',
])]
class QccBatch extends Model
{
    /** @use HasFactory<QccBatchFactory> */
    use Auditable, HasFactory;

    protected $table = 'qcc_batches';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_on' => 'date:Y-m-d',
            'ends_on' => 'date:Y-m-d',
            'participant_target' => 'integer',
            'registration_starts_on' => 'date:Y-m-d',
            'registration_ends_on' => 'date:Y-m-d',
            'selection_starts_on' => 'date:Y-m-d',
            'selection_ends_on' => 'date:Y-m-d',
            'execution_starts_on' => 'date:Y-m-d',
            'execution_ends_on' => 'date:Y-m-d',
            'status' => BatchStatus::class,
            'configuration_json' => 'array',
        ];
    }

    /**
     * The active batch that started most recently.
     */
    public static function current(): ?self
    {
        return self::query()
            ->where('status', BatchStatus::Active->value)
            ->orderByDesc('starts_on')
            ->orderByDesc('id')
            ->first();
    }

    /**
     * Members fill in their competencies until proposal submission closes at the end of phase 1.
     * A batch without that date keeps them open.
     */
    public function acceptsCompetencyChanges(?CarbonInterface $today = null): bool
    {
        $today ??= today(config('app.timezone'));

        return $this->registration_ends_on === null || $today->lte($this->registration_ends_on);
    }

    public function auditEventPrefix(): string
    {
        return 'batch';
    }

    public function auditLabel(): string
    {
        return $this->code.' - '.$this->name;
    }
}
