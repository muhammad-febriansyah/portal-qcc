<?php

namespace App\Models;

use App\Concerns\Auditable;
use Database\Factories\CompetencyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Competency extends Model
{
    /** @use HasFactory<CompetencyFactory> */
    use Auditable, HasFactory;

    protected $table = 'competency_master';

    protected $fillable = [
        'group_id',
        'code',
        'name',
        'description',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(CompetencyGroup::class, 'group_id');
    }

    public function auditEventPrefix(): string
    {
        return 'competency';
    }

    public function auditLabel(): string
    {
        return $this->code.' - '.$this->name;
    }
}
