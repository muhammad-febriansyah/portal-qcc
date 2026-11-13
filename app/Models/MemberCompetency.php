<?php

namespace App\Models;

use App\Concerns\Auditable;
use Database\Factories\MemberCompetencyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A person's self-assessed level for one competency.
 *
 * @property int $self_level
 * @property int $user_id
 * @property int $competency_id
 */
class MemberCompetency extends Model
{
    public const MAX_PER_USER = 10;

    /** @use HasFactory<MemberCompetencyFactory> */
    use Auditable, HasFactory;

    protected $table = 'member_competencies';

    /** @var list<string> */
    protected $fillable = ['user_id', 'competency_id', 'self_level'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'self_level' => 'integer',
        ];
    }

    /**
     * Competencies stay editable until proposal submission of the current batch closes.
     */
    public static function editingIsOpen(): bool
    {
        return QccBatch::current()?->acceptsCompetencyChanges() ?? true;
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Competency, $this> */
    public function competency(): BelongsTo
    {
        return $this->belongsTo(Competency::class);
    }

    public function auditEventPrefix(): string
    {
        return 'member_competency';
    }

    public function auditLabel(): string
    {
        $competency = $this->competency;

        return $competency ? $competency->code.' - '.$competency->name : 'Kompetensi #'.$this->competency_id;
    }
}
