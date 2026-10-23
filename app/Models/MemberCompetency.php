<?php

namespace App\Models;

use App\Concerns\Auditable;
use Database\Factories\MemberCompetencyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A person's self-assessed level for one competency. The verified level is kept
 * apart and is never changed by the person.
 *
 * @property int $self_level
 * @property int|null $verified_level
 * @property string|null $verification_status
 * @property string|null $verification_note
 * @property int $user_id
 * @property int $competency_id
 */
class MemberCompetency extends Model
{
    public const STATUS_PENDING = 'PENDING';

    public const STATUS_VERIFIED = 'VERIFIED';

    public const STATUS_REJECTED = 'REJECTED';

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
            'verified_level' => 'integer',
            'verified_at' => 'datetime',
        ];
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
