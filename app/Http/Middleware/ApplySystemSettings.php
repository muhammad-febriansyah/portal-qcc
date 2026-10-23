<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class ApplySystemSettings
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $settings = Cache::remember('system-settings.global', now()->addMinutes(5), function (): array {
            return DB::table('system_settings')
                ->whereIn('key', [
                    'app_name', 'timezone', 'keywords', 'site_description', 'logo_path', 'favicon_path',
                    'attachments_storage_disk', 'notifications_in_app', 'notifications_email',
                    'attachments_allowed_types', 'attachments_max_size_mb', 'attachments_max_files',
                    'attachments_retention_days',
                ])
                ->pluck('value', 'key')
                ->all();
        });

        config([
            'app.name' => $settings['app_name'] ?? config('app.name'),
            'app.timezone' => $settings['timezone'] ?? config('app.timezone'),
            'app.portal_keywords' => $settings['keywords'] ?? '',
            'app.portal_description' => $settings['site_description'] ?? '',
            'app.portal_logo_url' => isset($settings['logo_path']) ? Storage::disk('public')->url($settings['logo_path']) : null,
            'app.portal_favicon_url' => isset($settings['favicon_path']) ? Storage::disk('public')->url($settings['favicon_path']) : null,
            'filesystems.default' => $settings['attachments_storage_disk'] ?? config('filesystems.default'),
            'app.notifications.channels.in_app' => filter_var($settings['notifications_in_app'] ?? '1', FILTER_VALIDATE_BOOLEAN),
            'app.notifications.channels.email' => filter_var($settings['notifications_email'] ?? '0', FILTER_VALIDATE_BOOLEAN),
            'qcc.attachments.allowed_types' => json_decode($settings['attachments_allowed_types'] ?? '["pdf","docx","xlsx","png","jpg","jpeg","webp"]', true),
            'qcc.attachments.max_size_mb' => (int) ($settings['attachments_max_size_mb'] ?? 50),
            'qcc.attachments.max_files' => (int) ($settings['attachments_max_files'] ?? 5),
            'qcc.attachments.retention_days' => (int) ($settings['attachments_retention_days'] ?? 365),
        ]);
        date_default_timezone_set(config('app.timezone'));

        return $next($request);
    }
}
