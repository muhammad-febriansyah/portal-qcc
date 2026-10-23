<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * @property int $version_number
 * @property-read RubricSet|null $rubricSet
 */
class RubricVersion extends Model
{
    use Auditable;

    /** @var list<string> */
    protected $fillable = ['rubric_set_id', 'version_number', 'publication_status'];

    protected static function booted(): void
    {
        static::updating(function (self $version): void {
            if ($version->getOriginal('publication_status') === 'published') {
                throw new LogicException('Versi rubrik yang dipublikasikan tidak dapat diubah.');
            }
        });

        static::deleting(function (self $version): void {
            if ($version->publication_status === 'published') {
                throw new LogicException('Versi rubrik yang dipublikasikan tidak dapat dihapus.');
            }
        });
    }

    /** @return BelongsTo<RubricSet, $this> */
    public function rubricSet(): BelongsTo
    {
        return $this->belongsTo(RubricSet::class);
    }

    /** @return HasMany<RubricCriterion, $this> */
    public function criteria(): HasMany
    {
        return $this->hasMany(RubricCriterion::class)->orderBy('position');
    }

    public function auditEventPrefix(): string
    {
        return 'rubric_version';
    }

    public function auditLabel(): string
    {
        return $this->rubricSet->name.' v'.$this->version_number;
    }
}
