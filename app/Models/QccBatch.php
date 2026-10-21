<?php

namespace App\Models;

use App\Concerns\Auditable;
use App\Enums\BatchStatus;
use Database\Factories\QccBatchFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'name', 'starts_on', 'ends_on', 'status'])]
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
            'status' => BatchStatus::class,
            'configuration_json' => 'array',
        ];
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
