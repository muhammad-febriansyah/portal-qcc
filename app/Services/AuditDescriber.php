<?php

namespace App\Services;

use App\Enums\BatchStatus;
use App\Enums\CompetencyLevel;
use App\Models\AuditLog;
use App\Models\CompetencyGroup;
use App\Models\Organization;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * Turns raw audit entries into sentences and field changes a non-technical reader understands.
 */
class AuditDescriber
{
    /**
     * @var array<string, string>
     */
    private const FIELD_LABELS = [
        'name' => 'Nama',
        'email' => 'Email',
        'sim_id' => 'SIM ID',
        'organization_id' => 'Organisasi',
        'self_level' => 'Level saya',
        'verified_level' => 'Level terverifikasi',
        'verification_status' => 'Status verifikasi',
        'verification_note' => 'Catatan verifikasi',
        'competency_id' => 'Kompetensi',
        'is_active' => 'Status',
        'role' => 'Role',
        'permissions' => 'Permission',
        'code' => 'Kode',
        'starts_on' => 'Tanggal mulai',
        'ends_on' => 'Tanggal selesai',
        'status' => 'Status',
        'group_id' => 'Kelompok',
        'description' => 'Definisi',
        'key' => 'Pengaturan',
        'value' => 'Nilai',
        'updated_by' => 'Diubah oleh',
        'app_name' => 'Nama Website',
        'keywords' => 'Keyword',
        'site_description' => 'Deskripsi Website',
        'logo_path' => 'Logo Website',
        'favicon_path' => 'Favicon Website',
        'project_id' => 'Proyek',
        'reviewer_id' => 'Reviewer',
        'granted_by' => 'Ditugaskan oleh',
        'scope' => 'Ruang review',
        'expires_at' => 'Berlaku sampai',
    ];

    /**
     * @param  array<int|string, string>  $organizations
     * @param  array<int|string, string>  $groups
     */
    public function __construct(
        private readonly array $organizations = [],
        private readonly array $groups = [],
    ) {}

    /**
     * Preload the names that logged IDs point to, in two queries for the whole page.
     *
     * @param  Collection<int, AuditLog>  $logs
     */
    public static function forLogs(Collection $logs): self
    {
        $organizationIds = [];
        $groupIds = [];

        foreach ($logs as $log) {
            foreach ([$log->before_json, $log->after_json] as $data) {
                if (isset($data['organization_id'])) {
                    $organizationIds[] = $data['organization_id'];
                }

                if (isset($data['group_id'])) {
                    $groupIds[] = $data['group_id'];
                }
            }
        }

        return new self(
            $organizationIds === [] ? [] : Organization::query()->whereIn('id', array_unique($organizationIds))->pluck('name', 'id')->all(),
            $groupIds === [] ? [] : CompetencyGroup::query()->whereIn('id', array_unique($groupIds))->pluck('name', 'id')->all(),
        );
    }

    public function describe(AuditLog $log): string
    {
        $actor = $log->actor->name ?? 'Sistem';
        $subject = $log->subject_label ?? 'data';
        $fields = $this->changedFieldNames($log);

        return match ($log->event) {
            'auth.login' => "{$actor} masuk ke ".config('app.name'),
            'auth.logout' => "{$actor} keluar dari ".config('app.name'),
            'auth.login_failed' => 'Percobaan masuk gagal untuk email '.($log->subject_label ?? 'tidak diketahui'),
            'data.exported' => "{$actor} mengekspor {$subject}".$this->exportDetails($log),
            'user.created' => "{$actor} menambahkan anggota {$subject}",
            'user.updated' => "{$actor} mengubah data anggota {$subject}".$fields,
            'user.activated' => "{$actor} mengaktifkan akun {$subject}",
            'user.deactivated' => "{$actor} menonaktifkan akun {$subject}",
            'user.password_reset' => "{$actor} mereset password {$subject}",
            'user.avatar_changed' => "{$actor} mengubah foto profil",
            'user.avatar_removed' => "{$actor} menghapus foto profil",
            'user.role_changed' => $this->describeRoleChange($log, $actor, $subject),
            'role.permissions_updated' => "{$actor} memperbarui permission role {$subject}",
            'rubric.created' => "{$actor} menambahkan draft rubrik {$subject}",
            'rubric.updated' => "{$actor} mengubah rubrik {$subject}".$fields,
            'rubric.deleted' => "{$actor} menghapus rubrik {$subject}",
            'rubric_version.created' => "{$actor} menambahkan versi rubrik {$subject}",
            'rubric_version.updated' => "{$actor} mengubah versi rubrik {$subject}".$fields,
            'rubric_version.deleted' => "{$actor} menghapus versi rubrik {$subject}",
            'rubric_criterion.created' => "{$actor} menambahkan kriteria {$subject}",
            'rubric_criterion.updated' => "{$actor} mengubah kriteria {$subject}".$fields,
            'rubric_criterion.deleted' => "{$actor} menghapus kriteria {$subject}",
            'batch.created' => "{$actor} menambahkan batch {$subject}",
            'batch.updated' => "{$actor} mengubah batch {$subject}".$fields,
            'batch.deleted' => "{$actor} menghapus batch {$subject}",
            'organization.created' => "{$actor} menambahkan organisasi {$subject}",
            'organization.updated' => "{$actor} mengubah organisasi {$subject}".$fields,
            'competency.created' => "{$actor} menambahkan kompetensi {$subject}",
            'competency.updated' => "{$actor} mengubah kompetensi {$subject}".$fields,
            'competency.deleted' => "{$actor} menghapus kompetensi {$subject}",
            'competency.imported' => "{$actor} mengimpor master kompetensi (".(int) ($log->after_json['created'] ?? 0).' dibuat, '.(int) ($log->after_json['updated'] ?? 0).' diperbarui)',
            'member_competency.created' => "{$actor} menambahkan kompetensi {$subject} ke daftarnya",
            'member_competency.updated' => "{$actor} mengubah level kompetensi {$subject}".$fields,
            'member_competency.deleted' => "{$actor} menghapus kompetensi {$subject} dari daftarnya",
            'member_competency.verified' => "{$actor} memverifikasi kompetensi {$subject}",
            'member_competency.rejected' => "{$actor} meminta perbaikan kompetensi {$subject}",
            'scoring.run_requested' => "{$actor} meminta penilaian AI untuk {$subject}",
            'scoring.completed' => "Penilaian AI selesai untuk {$subject}",
            'scoring.insufficient_evidence' => "Penilaian AI untuk {$subject} dihentikan karena evidence belum cukup",
            'scoring.failed' => "Penilaian AI untuk {$subject} gagal diproses",
            'scoring.overridden' => "{$actor} mengubah nilai AI pada {$subject}",
            'scoring.approved' => "{$actor} menyetujui nilai final {$subject}",
            'scoring.revision_requested' => "{$actor} meminta revisi untuk {$subject}",
            'team_matching.requested' => "{$actor} meminta rekomendasi tim AI untuk {$subject}",
            'team_matching.completed' => "Rekomendasi tim AI selesai untuk {$subject}",
            'team_matching.confirmed' => "{$actor} mengonfirmasi tim untuk {$subject}",
            'team_matching.failed' => "Rekomendasi tim AI untuk {$subject} gagal diproses",
            'proposal.submitted' => "{$actor} mengajukan proposal {$subject} untuk direview",
            'proposal.resubmitted' => "{$actor} mengajukan ulang proposal {$subject}",
            'proposal.review.started' => "{$actor} memulai review proposal {$subject}",
            'proposal.approve' => "{$actor} menyetujui proposal {$subject}",
            'proposal.request.revision' => "{$actor} meminta revisi proposal {$subject}",
            'proposal.reject' => "{$actor} menolak proposal {$subject}",
            'system_setting.created' => "{$actor} menambahkan pengaturan sistem {$subject}".$fields,
            'system_setting.updated' => "{$actor} mengubah pengaturan sistem {$subject}".$fields,
            'evidence.created' => "{$actor} mengunggah evidence {$subject}",
            'evidence.updated' => "{$actor} memperbarui status evidence {$subject}".$fields,
            'evidence_link.created' => "{$actor} mengaitkan {$subject} ke proyek atau step",
            'review_assignment.created' => "{$actor} menugaskan reviewer untuk {$subject}",
            'review_assignment.updated' => "{$actor} memperbarui penugasan reviewer {$subject}".$fields,
            'review_assignment.revoked' => "{$actor} mencabut penugasan reviewer {$subject}",
            default => "{$actor}: ".$log->eventLabel().($log->subject_label ? " ({$subject})" : ''),
        };
    }

    /**
     * Each changed field as before and after in plain words.
     *
     * @return list<array{label: string, before: string, after: string}>
     */
    public function changes(AuditLog $log): array
    {
        $before = $log->before_json ?? [];
        $after = $log->after_json ?? [];
        $rows = [];

        foreach (array_unique([...array_keys($before), ...array_keys($after)]) as $field) {
            $rows[] = [
                'label' => self::FIELD_LABELS[$field] ?? ucfirst(str_replace('_', ' ', (string) $field)),
                'before' => $this->value((string) $field, $before[$field] ?? null),
                'after' => $this->value((string) $field, $after[$field] ?? null),
            ];
        }

        return $rows;
    }

    private function describeRoleChange(AuditLog $log, string $actor, string $subject): string
    {
        $from = $log->before_json['role'] ?? null;
        $to = $log->after_json['role'] ?? null;

        return $from === null
            ? "{$actor} menetapkan role {$to} untuk {$subject}"
            : "{$actor} mengubah role {$subject} dari {$from} menjadi {$to}";
    }

    private function exportDetails(AuditLog $log): string
    {
        $count = $log->after_json['row_count'] ?? null;

        return is_numeric($count) ? ' sebanyak '.number_format((int) $count, 0, ',', '.').' baris' : '';
    }

    private function changedFieldNames(AuditLog $log): string
    {
        $labels = array_map(
            fn (string $field): string => strtolower(self::FIELD_LABELS[$field] ?? str_replace('_', ' ', $field)),
            array_keys($log->after_json ?? []),
        );

        return $labels === [] ? '' : ' ('.implode(', ', array_unique($labels)).')';
    }

    private function value(string $field, mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        return match ($field) {
            'is_active' => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'Aktif' : 'Nonaktif',
            'self_level', 'verified_level' => CompetencyLevel::labelFor((int) $value) ?? (string) $value,
            'verification_status' => match (strtoupper((string) $value)) {
                'PENDING' => 'Menunggu verifikasi',
                'VERIFIED' => 'Terverifikasi',
                'REJECTED' => 'Perlu perbaikan',
                default => (string) $value,
            },
            'organization_id' => $this->organizations[$value] ?? "#{$value}",
            'group_id' => $this->groups[$value] ?? "#{$value}",
            'status' => BatchStatus::tryFrom((string) $value)?->label() ?? (string) $value,
            'starts_on', 'ends_on' => $this->formatDate((string) $value),
            default => is_scalar($value) ? (string) $value : (json_encode($value, JSON_UNESCAPED_UNICODE) ?: '—'),
        };
    }

    private function formatDate(string $value): string
    {
        $date = Carbon::parse($value);
        $date->locale('id');

        return $date->isoFormat('D MMM YYYY');
    }
}
