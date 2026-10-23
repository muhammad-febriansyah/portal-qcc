<?php

namespace Database\Seeders;

use App\Models\SystemSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

class SystemSettingSeeder extends Seeder
{
    /**
     * Give the website a name, logo and favicon so pages never fall back to
     * hardcoded branding. Existing values are never overwritten, so anything an
     * admin changed in Pengaturan Sistem is kept. Idempotent.
     */
    public function run(): void
    {
        $this->seedIfMissing('app_name', 'Portal QCC');
        $this->seedFileIfMissing('logo_path', public_path('assets/logo-simgroup.png'), 'branding/logo.png');
        $this->seedFileIfMissing('favicon_path', public_path('assets/logo-simgroup-mark.png'), 'branding/favicon.png');

        Cache::forget('system-settings.global');
    }

    private function seedIfMissing(string $key, string $value): void
    {
        SystemSetting::query()->firstOrCreate(['key' => $key], ['value' => $value]);
    }

    private function seedFileIfMissing(string $key, string $source, string $target): void
    {
        if (SystemSetting::query()->where('key', $key)->exists() || ! File::exists($source)) {
            return;
        }

        Storage::disk('public')->put($target, File::get($source));
        $this->seedIfMissing($key, $target);
    }
}
