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
        'role.permissions_updated' => 'Permission role diperbarui',
        'rubric.created' => 'Draft rubrik ditambahkan',
        'rubric.updated' => 'Rubrik diperbarui',
        'rubric.deleted' => 'Rubrik dihapus',
        'rubric_version.created' => 'Versi rubrik ditambahkan',
        'rubric_version.updated' => 'Versi rubrik diperbarui',
        'rubric_version.deleted' => 'Versi rubrik dihapus',
        'rubric_criterion.created' => 'Kriteria rubrik ditambahkan',
        'rubric_criterion.updated' => 'Kriteria rubrik diperbarui',
        'rubric_criterion.deleted' => 'Kriteria rubrik dihapus',
        'user.password_reset' => 'Password direset',
        'user.avatar_changed' => 'Foto profil diubah',
        'user.avatar_removed' => 'Foto profil dihapus',
        'batch.created' => 'Batch ditambahkan',
        'batch.updated' => 'Batch diperbarui',
        'batch.deleted' => 'Batch dihapus',
        'organization.created' => 'Organisasi ditambahkan',
        'organization.updated' => 'Organisasi diperbarui',
        'competency.created' => 'Kompetensi ditambahkan',
        'competency.updated' => 'Kompetensi diperbarui',
        'competency.deleted' => 'Kompetensi dihapus',
        'competency.imported' => 'Master kompetensi diimpor',
        'member_competency.created' => 'Kompetensi pribadi ditambahkan',
        'member_competency.updated' => 'Level kompetensi diubah',
        'member_competency.deleted' => 'Kompetensi pribadi dihapus',
        'member_competency.verified' => 'Kompetensi anggota diverifikasi',
        'member_competency.rejected' => 'Kompetensi anggota perlu diperbaiki',
        'scoring.run_requested' => 'Penilaian AI diminta',
        'scoring.completed' => 'Penilaian AI selesai',
        'scoring.insufficient_evidence' => 'Evidence belum cukup',
        'scoring.failed' => 'Penilaian AI gagal',
        'scoring.overridden' => 'Nilai AI diubah reviewer',
        'scoring.approved' => 'Nilai disetujui',
        'scoring.revision_requested' => 'Revisi diminta',
        'team_matching.requested' => 'Rekomendasi tim AI diminta',
        'team_matching.completed' => 'Rekomendasi tim AI selesai',
        'team_matching.failed' => 'Rekomendasi tim AI gagal',
        'team_matching.confirmed' => 'Tim dikonfirmasi',
        'system_setting.created' => 'Pengaturan sistem ditambahkan',
        'system_setting.updated' => 'Pengaturan sistem diperbarui',
        'evidence.created' => 'Evidence ditambahkan',
        'evidence.updated' => 'Status evidence diperbarui',
        'evidence_link.created' => 'Evidence dikaitkan',
        'review_assignment.created' => 'Reviewer ditugaskan',
        'review_assignment.updated' => 'Penugasan reviewer diperbarui',
        'review_assignment.revoked' => 'Penugasan reviewer dicabut',
        'data.exported' => 'Data diekspor',
    ];

    /**
     * Event prefix to module name, used for filtering and display.
     *
     * @var array<string, string>
     */
    public const MODULE_LABELS = [
        'auth' => 'Autentikasi',
        'user' => 'Anggota',
        'role' => 'Role & Permission',
        'rubric' => 'Rubrik',
        'rubric_version' => 'Rubrik',
        'rubric_criterion' => 'Rubrik',
        'batch' => 'Batch',
        'organization' => 'Organisasi',
        'competency' => 'Kompetensi',
        'member_competency' => 'Kompetensi Saya',
        'scoring' => 'Penilaian AI',
        'team_matching' => 'Rekomendasi Tim AI',
        'system_setting' => 'Pengaturan Sistem',
        'evidence' => 'Evidence',
        'evidence_link' => 'Evidence',
        'review_assignment' => 'Penugasan Reviewer',
        'data' => 'Ekspor Data',
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
