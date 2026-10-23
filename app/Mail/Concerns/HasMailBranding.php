<?php

namespace App\Mail\Concerns;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Branding for emails. Emails are sent from the queue, where the middleware that applies the
 * system settings does not run, so the values are read from the settings here.
 */
trait HasMailBranding
{
    /**
     * Logo for the email header. The logo uploaded in the system settings is used when it
     * exists, otherwise the one shipped with the portal. It is embedded in the email, so
     * it shows even where the portal address is not reachable from the mail client.
     */
    protected function logoPath(): string
    {
        $setting = $this->systemSetting('logo_path');

        if ($setting !== null && Storage::disk('public')->exists($setting)) {
            return Storage::disk('public')->path($setting);
        }

        return public_path('assets/logo-simgroup.png');
    }

    protected function appName(): string
    {
        return $this->systemSetting('app_name') ?? (string) config('app.name');
    }

    protected function timezone(): string
    {
        return $this->systemSetting('timezone') ?? (string) config('app.timezone');
    }

    /**
     * @return array{logoPath: string, appName: string, portalUrl: string}
     */
    protected function brandData(): array
    {
        return [
            'logoPath' => $this->logoPath(),
            'appName' => $this->appName(),
            'portalUrl' => url('/'),
        ];
    }

    private function systemSetting(string $key): ?string
    {
        $value = DB::table('system_settings')->where('key', $key)->value('value');

        return is_string($value) && $value !== '' ? $value : null;
    }
}
