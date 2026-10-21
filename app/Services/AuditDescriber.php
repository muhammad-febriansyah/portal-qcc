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
        'competency_id' => 'Kompetensi',
        'is_active' => 'Status',
        'role' => 'Role',
        'code' => 'Kode',
        'starts_on' => 'Tanggal mulai',
        'ends_on' => 'Tanggal selesai',
        'status' => 'Status',
        'group_id' => 'Kelompok',
        'description' => 'Definisi',
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
        $actor = $log->actor?->name ?? 'Sistem';
        $subject = $log->subject_label ?? 'data';
        $fields = $this->changedFieldNames($log);

        return match ($log->event) {
            'auth.login' => "{$actor} masuk ke Portal QCC",
            'auth.logout' => "{$actor} keluar dari Portal QCC",
            'auth.login_failed' => 'Percobaan masuk gagal untuk email '.($log->subject_label ?? 'tidak diketahui'),
            'user.created' => "{$actor} menambahkan anggota {$subject}",
            'user.updated' => "{$actor} mengubah data anggota {$subject}".$fields,
            'user.activated' => "{$actor} mengaktifkan akun {$subject}",
            'user.deactivated' => "{$actor} menonaktifkan akun {$subject}",
            'user.password_reset' => "{$actor} mereset password {$subject}",
            'user.avatar_changed' => "{$actor} mengubah foto profil",
            'user.avatar_removed' => "{$actor} menghapus foto profil",
            'user.role_changed' => $this->describeRoleChange($log, $actor, $subject),
            'batch.created' => "{$actor} menambahkan batch {$subject}",
            'batch.updated' => "{$actor} mengubah batch {$subject}".$fields,
            'batch.deleted' => "{$actor} menghapus batch {$subject}",
            'organization.created' => "{$actor} menambahkan organisasi {$subject}",
            'organization.updated' => "{$actor} mengubah organisasi {$subject}".$fields,
            'competency.created' => "{$actor} menambahkan kompetensi {$subject}",
            'competency.updated' => "{$actor} mengubah kompetensi {$subject}".$fields,
            'competency.deleted' => "{$actor} menghapus kompetensi {$subject}",
            'member_competency.created' => "{$actor} menambahkan kompetensi {$subject} ke daftarnya",
            'member_competency.updated' => "{$actor} mengubah level kompetensi {$subject}".$fields,
            'member_competency.deleted' => "{$actor} menghapus kompetensi {$subject} dari daftarnya",
            'scoring.run_requested' => "{$actor} meminta penilaian AI untuk {$subject}",
            'scoring.completed' => "Penilaian AI selesai untuk {$subject}",
            'scoring.insufficient_evidence' => "Penilaian AI untuk {$subject} dihentikan karena evidence belum cukup",
            'scoring.failed' => "Penilaian AI untuk {$subject} gagal diproses",
            'scoring.overridden' => "{$actor} mengubah nilai AI pada {$subject}",
            'scoring.approved' => "{$actor} menyetujui nilai final {$subject}",
            'scoring.revision_requested' => "{$actor} meminta revisi untuk {$subject}",
            'team_matching.requested' => "{$actor} meminta rekomendasi tim AI untuk {$subject}",
            'team_matching.completed' => "Rekomendasi tim AI selesai untuk {$subject}",
            'team_matching.failed' => "Rekomendasi tim AI untuk {$subject} gagal diproses",
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
            'organization_id' => $this->organizations[$value] ?? "#{$value}",
            'group_id' => $this->groups[$value] ?? "#{$value}",
            'status' => BatchStatus::tryFrom((string) $value)?->label() ?? (string) $value,
            'starts_on', 'ends_on' => Carbon::parse((string) $value)->locale('id')->isoFormat('D MMM YYYY'),
            default => is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE),
        };
    }
}
