<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateSystemPoliciesRequest;
use App\Http\Requests\UpdateSystemSettingsRequest;
use App\Mail\SmtpTestMail;
use App\Models\SystemSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Throwable;

class SystemSettingsController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('system-settings.view');

        $settings = SystemSetting::query()->pluck('value', 'key');
        $logoPath = $settings->get('logo_path');
        $faviconPath = $settings->get('favicon_path');
        $settingsApiKey = $settings->get('ai_api_key');
        $aiModel = $settings->get('ai_model', config('scoring.openai.model', ''));
        $aiModelOptions = config('scoring.openai.models', []);

        if (filled($aiModel) && ! in_array($aiModel, array_column($aiModelOptions, 'id'), true)) {
            $aiModelOptions[] = ['id' => $aiModel, 'name' => $aiModel.' (tersimpan)'];
        }

        return Inertia::render('admin/settings/index', [
            'general' => [
                'applicationName' => $settings->get('app_name', config('app.name')),
                'keywords' => $settings->get('keywords', ''),
                'description' => $settings->get('site_description', ''),
                'language' => config('app.locale'),
                'timezone' => $settings->get('timezone', config('app.timezone')),
                'logoUrl' => $logoPath ? Storage::disk('public')->url($logoPath) : null,
                'faviconUrl' => $faviconPath ? Storage::disk('public')->url($faviconPath) : null,
            ],
            'policies' => [
                'notifications' => [
                    'inApp' => filter_var($settings->get('notifications_in_app', '1'), FILTER_VALIDATE_BOOLEAN),
                    'email' => filter_var($settings->get('notifications_email', '0'), FILTER_VALIDATE_BOOLEAN),
                ],
                'attachments' => [
                    'storageDisk' => $settings->get('attachments_storage_disk', config('filesystems.default', 'local')),
                    'storageOptions' => blank(config('filesystems.disks.s3.bucket')) ? ['local'] : ['local', 's3'],
                    'allowedTypes' => json_decode($settings->get('attachments_allowed_types', '["pdf","docx","xlsx","png","jpg","jpeg","webp"]'), true),
                    'maxSizeMb' => (int) $settings->get('attachments_max_size_mb', 50),
                    'maxFiles' => (int) $settings->get('attachments_max_files', 5),
                    'retentionDays' => (int) $settings->get('attachments_retention_days', 365),
                ],
                'ai' => [
                    'enabled' => filter_var(
                        $settings->get(
                            'ai_enabled',
                            filled(config('scoring.openai.key')) && filled(config('scoring.openai.model')) ? '1' : '0',
                        ),
                        FILTER_VALIDATE_BOOLEAN,
                    ),
                    'model' => $aiModel,
                    'modelOptions' => $aiModelOptions,
                    'hasApiKey' => filled($settingsApiKey) || filled(config('scoring.openai.key')),
                ],
                'sso' => [
                    'title' => 'Single sign-on',
                    'decision' => 'DEC-20',
                    'description' => 'Pilihan SSO perusahaan atau login lokal belum ditetapkan.',
                ],
            ],
            'canManage' => $request->user()->can('system-settings.manage'),
        ]);
    }

    public function testEmail(Request $request): RedirectResponse
    {
        Gate::authorize('system-settings.manage');

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
        ]);

        try {
            Mail::mailer('smtp')->to($validated['email'])->send(new SmtpTestMail);
        } catch (Throwable $exception) {
            Log::warning('SMTP test email failed.', [
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);

            return back()->withErrors([
                'email' => 'Email uji gagal dikirim. Periksa konfigurasi SMTP dan log aplikasi.',
            ]);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'SMTP menerima email uji. Periksa inbox dan folder spam.',
        ]);

        return back();
    }

    public function updatePolicies(UpdateSystemPoliciesRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $values = [
            'notifications_in_app' => (string) (int) $validated['notifications_in_app'],
            'notifications_email' => (string) (int) $validated['notifications_email'],
            'attachments_storage_disk' => $validated['attachments_storage_disk'],
            'attachments_allowed_types' => json_encode(array_values(array_unique($validated['attachments_allowed_types'])), JSON_THROW_ON_ERROR),
            'attachments_max_size_mb' => (string) $validated['attachments_max_size_mb'],
            'attachments_max_files' => (string) $validated['attachments_max_files'],
            'attachments_retention_days' => (string) $validated['attachments_retention_days'],
            'ai_enabled' => (string) (int) $validated['ai_enabled'],
            'ai_model' => $validated['ai_model'] ?? '',
        ];

        if (filled($validated['ai_api_key'] ?? null)) {
            $values['ai_api_key'] = Crypt::encryptString($validated['ai_api_key']);
        }

        DB::transaction(function () use ($request, $values): void {
            foreach ($values as $key => $value) {
                SystemSetting::query()->updateOrCreate(
                    ['key' => $key],
                    ['value' => $value, 'updated_by' => $request->user()->id],
                );
            }
        });

        Cache::forget('system-settings.global');
        Cache::forget('system-settings.ai');
        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Kebijakan sistem berhasil disimpan.',
        ]);

        return back();
    }

    public function update(UpdateSystemSettingsRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $values = [
            'app_name' => $validated['app_name'],
            'keywords' => $validated['keywords'] ?? '',
            'site_description' => $validated['site_description'] ?? '',
            'timezone' => $validated['timezone'],
        ];
        $oldPaths = SystemSetting::query()
            ->whereIn('key', ['logo_path', 'favicon_path'])
            ->pluck('value', 'key');
        $newPaths = [];

        try {
            foreach (['logo' => 'logo_path', 'favicon' => 'favicon_path'] as $field => $key) {
                if ($request->hasFile($field)) {
                    $path = $request->file($field)->storePublicly('system-settings', 'public');

                    if ($path === false) {
                        throw new \RuntimeException("File {$field} gagal disimpan.");
                    }

                    $newPaths[$key] = $path;
                }
            }

            $values = [...$values, ...$newPaths];

            DB::transaction(function () use ($request, $values): void {
                foreach ($values as $key => $value) {
                    SystemSetting::query()->updateOrCreate(
                        ['key' => $key],
                        ['value' => $value, 'updated_by' => $request->user()->id],
                    );
                }
            });
        } catch (Throwable $exception) {
            Storage::disk('public')->delete(array_values($newPaths));

            throw $exception;
        }

        foreach ($newPaths as $key => $path) {
            if ($oldPaths->has($key)) {
                Storage::disk('public')->delete($oldPaths->get($key));
            }
        }

        Cache::forget('system-settings.global');
        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Pengaturan sistem berhasil disimpan.',
        ]);

        return back();
    }
}
