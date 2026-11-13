<?php

use App\Models\SystemSetting;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Spatie\Permission\Models\Permission;

function aiSettingsManager(): User
{
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('system-settings.manage', 'web'));

    return $user;
}

function savedAiSettings(bool $enabled = true): void
{
    SystemSetting::query()->updateOrCreate(['key' => 'ai_enabled'], ['value' => $enabled ? '1' : '0']);
    SystemSetting::query()->updateOrCreate(['key' => 'ai_model'], ['value' => 'gpt-5']);
    SystemSetting::query()->updateOrCreate(['key' => 'ai_api_key'], ['value' => Crypt::encryptString('sk-test-123')]);
}

beforeEach(function () {
    config()->set('scoring.openai.key', null);
    config()->set('scoring.openai.model', null);
});

test('a valid saved key and model report the connection as working, without sending any project data', function () {
    savedAiSettings();
    Http::fake(['api.openai.com/v1/models/gpt-5' => Http::response(['id' => 'gpt-5'], 200)]);

    $this->actingAs(aiSettingsManager())
        ->post(route('admin.settings.test-ai'))
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    Http::assertSentCount(1);
    Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
        && str_ends_with($request->url(), '/models/gpt-5')
        && $request->hasHeader('Authorization', 'Bearer sk-test-123')
        && $request->body() === '');
});

test('a rejected key is reported as invalid', function () {
    savedAiSettings();
    Http::fake(['api.openai.com/v1/models/*' => Http::response(['error' => ['message' => 'Incorrect API key']], 401)]);

    $this->actingAs(aiSettingsManager())
        ->post(route('admin.settings.test-ai'))
        ->assertSessionHasErrors(['ai_connection' => 'API key tidak valid atau sudah dicabut.']);
});

test('a model the key cannot see is reported with its name', function () {
    savedAiSettings();
    Http::fake(['api.openai.com/v1/models/*' => Http::response(['error' => ['message' => 'not found']], 404)]);

    $this->actingAs(aiSettingsManager())
        ->post(route('admin.settings.test-ai'))
        ->assertSessionHasErrors(['ai_connection' => 'Model gpt-5 tidak ditemukan untuk akun ini.']);
});

test('a network failure is reported without a server error', function () {
    savedAiSettings();
    Http::fake(fn () => throw new ConnectionException('timeout'));

    $this->actingAs(aiSettingsManager())
        ->post(route('admin.settings.test-ai'))
        ->assertSessionHasErrors('ai_connection');
});

test('nothing is sent while the integration is switched off or has no key', function () {
    savedAiSettings(enabled: false);
    Http::fake();

    $this->actingAs(aiSettingsManager())
        ->post(route('admin.settings.test-ai'))
        ->assertSessionHasErrors(['ai_connection']);

    Http::assertNothingSent();
});

test('users without system settings permission cannot run the test', function () {
    savedAiSettings();
    Http::fake();

    $this->actingAs(User::factory()->create())
        ->post(route('admin.settings.test-ai'))
        ->assertForbidden();

    Http::assertNotSent(fn (Request $request): bool => str_contains($request->url(), 'api.openai.com'));
});
