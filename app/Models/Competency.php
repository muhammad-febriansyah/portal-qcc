<?php

namespace App\Models;

use App\Concerns\Auditable;
use Database\Factories\CompetencyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A skill from the SIMGROUP QCC skill dictionary: a flat list of hard skills (H01, H02, ...)
 * and soft skills (S01, S02, ...), each with a plain-language definition.
 *
 * @property int|null $group_id
 * @property string $code
 * @property string $name
 * @property bool $is_active
 * @property-read CompetencyGroup|null $group
 */
class Competency extends Model
{
    /** Skill codes: H for hard skills, S for soft skills, then a two or three digit number. */
    public const CODE_PATTERN = '/^[HS]\d{2,3}$/';

    public const CODE_HINT = 'Kode harus berformat H01 (hard skill) atau S01 (soft skill).';

    /** @use HasFactory<CompetencyFactory> */
    use Auditable, HasFactory;

    protected $table = 'competency_master';

    protected $fillable = [
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

    /** @return BelongsTo<CompetencyGroup, $this> */
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
