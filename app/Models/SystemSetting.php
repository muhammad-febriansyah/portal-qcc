<?php

namespace App\Models;

use App\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    use Auditable {
        auditAttributes as protected auditAttributesWithoutSecrets;
    }

    protected $fillable = ['key', 'value', 'updated_by'];

    protected $hidden = ['updated_by'];

    /**
     * Exclude encrypted provider credentials from audit snapshots.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    protected function auditAttributes(array $attributes): array
    {
        if (($attributes['key'] ?? $this->key) === 'ai_api_key') {
            unset($attributes['value']);
        }

        return $this->auditAttributesWithoutSecrets($attributes);
    }

    public function auditEventPrefix(): string
    {
        return 'system_setting';
    }

    public function auditLabel(): string
    {
        return match ($this->key) {
            'app_name' => 'Nama Website',
            'timezone' => 'Zona waktu',
            'keywords' => 'Keyword',
            'site_description' => 'Deskripsi Website',
            'logo_path' => 'Logo Website',
            'favicon_path' => 'Favicon Website',
            'notifications_in_app' => 'Notifikasi in-app',
            'notifications_email' => 'Notifikasi email',
            'attachments_storage_disk' => 'Penyimpanan lampiran',
            'attachments_allowed_types' => 'Tipe lampiran',
            'attachments_max_size_mb' => 'Ukuran maksimum lampiran',
            'attachments_max_files' => 'Jumlah maksimum lampiran',
            'attachments_retention_days' => 'Retensi lampiran',
            'ai_enabled' => 'Status integrasi AI',
            'ai_model' => 'Model integrasi AI',
            'ai_api_key' => 'API key integrasi AI',
            default => 'Pengaturan sistem',
        };
    }
}
