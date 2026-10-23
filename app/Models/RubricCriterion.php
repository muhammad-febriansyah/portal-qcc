<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property int $position
 * @property string $code
 * @property string $name
 * @property string|null $weight_pct
 */
class RubricCriterion extends Model
{
    use Auditable;

    /** @var list<string> */
    protected $fillable = [
        'rubric_version_id',
        'code',
        'name',
        'description',
        'weight_pct',
        'position',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $criterion): void {
            if ($criterion->rubricVersion?->publication_status === 'published') {
                throw new LogicException('Kriteria pada versi rubrik terbit tidak dapat diubah.');
            }
        });

        static::updating(function (self $criterion): void {
            if ($criterion->rubricVersion?->publication_status === 'published') {
                throw new LogicException('Kriteria pada versi rubrik terbit tidak dapat diubah.');
            }
        });

        static::deleting(function (self $criterion): void {
            if ($criterion->rubricVersion?->publication_status === 'published') {
                throw new LogicException('Kriteria pada versi rubrik terbit tidak dapat dihapus.');
            }
        });
    }

    /** @return BelongsTo<RubricVersion, $this> */
    public function rubricVersion(): BelongsTo
    {
        return $this->belongsTo(RubricVersion::class);
    }

    public function auditEventPrefix(): string
    {
        return 'rubric_criterion';
    }

    public function auditLabel(): string
    {
        return $this->code.' - '.$this->name;
    }
}
