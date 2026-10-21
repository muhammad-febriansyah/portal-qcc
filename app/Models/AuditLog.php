<?php

namespace App\Models;

use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use LogicException;

/**
 * Append-only record of who did what. Rows can be created but never changed or removed.
 *
 * @property int $id
 * @property int|null $actor_id
 * @property string $event
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property string|null $subject_label
 * @property string $trace_id
 * @property array<string, mixed>|null $before_json
 * @property array<string, mixed>|null $after_json
 * @property string|null $reason
 * @property Carbon $occurred_at
 */
class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory;

    public $timestamps = false;

    protected $guarded = [];

    /**
     * Human readable names for known events. Unknown events fall back to their key.
     *
     * @var array<string, string>
     */
    public const EVENT_LABELS = [
        'auth.login' => 'Login berhasil',
        'auth.logout' => 'Logout',
        'auth.login_failed' => 'Login gagal',
        'user.created' => 'Anggota ditambahkan',
        'user.updated' => 'Anggota diperbarui',
        'user.activated' => 'Akun diaktifkan',
        'user.deactivated' => 'Akun dinonaktifkan',
        'user.role_changed' => 'Role diubah',
        'user.password_reset' => 'Password direset',
        'batch.created' => 'Batch ditambahkan',
        'batch.updated' => 'Batch diperbarui',
        'batch.deleted' => 'Batch dihapus',
        'organization.created' => 'Organisasi ditambahkan',
        'organization.updated' => 'Organisasi diperbarui',
        'competency.created' => 'Kompetensi ditambahkan',
        'competency.updated' => 'Kompetensi diperbarui',
        'competency.deleted' => 'Kompetensi dihapus',
    ];

    /**
     * Event prefix to module name, used for filtering and display.
     *
     * @var array<string, string>
     */
    public const MODULE_LABELS = [
        'auth' => 'Autentikasi',
        'user' => 'Anggota',
        'batch' => 'Batch',
        'organization' => 'Organisasi',
        'competency' => 'Kompetensi',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit log tidak dapat diubah.'));
        static::deleting(fn () => throw new LogicException('Audit log tidak dapat dihapus.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'before_json' => 'array',
            'after_json' => 'array',
            'occurred_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function eventLabel(): string
    {
        return self::EVENT_LABELS[$this->event] ?? Str::headline(str_replace('.', ' ', $this->event));
    }

    public function moduleLabel(): string
    {
        $module = Str::before($this->event, '.');

        return self::MODULE_LABELS[$module] ?? Str::headline($module);
    }
}
