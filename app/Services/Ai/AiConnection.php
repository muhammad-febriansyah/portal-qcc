<?php

namespace App\Services\Ai;

use App\Services\Ai\Exceptions\AiUnavailable;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reads the AI provider settings (Pengaturan Sistem, falling back to the
 * environment). The key stays on the server and is only decrypted when used.
 */
class AiConnection
{
    public const MESSAGE_NOT_READY = 'Integrasi AI belum diatur. Minta Super Admin mengisi penyedia, model, dan API key di Pengaturan Sistem.';

    /**
     * @return array{key: string, model: string}
     *
     * @throws AiUnavailable
     */
    public function credentials(): array
    {
        $settings = Schema::hasTable('system_settings')
            ? DB::table('system_settings')->whereIn('key', ['ai_enabled', 'ai_model', 'ai_api_key'])->pluck('value', 'key')->all()
            : [];

        $configuredKey = config('scoring.openai.key');
        $configuredModel = config('scoring.openai.model');
        $isEnabled = array_key_exists('ai_enabled', $settings)
            ? filter_var($settings['ai_enabled'], FILTER_VALIDATE_BOOLEAN)
            : filled($configuredKey) && filled($configuredModel);

        if (! $isEnabled) {
            throw new AiUnavailable('Integrasi AI dinonaktifkan di Pengaturan Sistem.', false, 'ai_not_configured');
        }

        try {
            $key = filled($settings['ai_api_key'] ?? null)
                ? Crypt::decryptString($settings['ai_api_key'])
                : $configuredKey;
        } catch (DecryptException) {
            throw new AiUnavailable('API key integrasi AI tidak dapat dibaca.', false, 'ai_not_configured');
        }

        $model = filled($settings['ai_model'] ?? null) ? $settings['ai_model'] : $configuredModel;

        if (! is_string($key) || $key === '' || ! is_string($model) || $model === '') {
            throw new AiUnavailable(self::MESSAGE_NOT_READY, false, 'ai_not_configured');
        }

        return ['key' => $key, 'model' => $model];
    }

    public function isReady(): bool
    {
        try {
            $this->credentials();
        } catch (AiUnavailable) {
            return false;
        }

        return true;
    }
}
