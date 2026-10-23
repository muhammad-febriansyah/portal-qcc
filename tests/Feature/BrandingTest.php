<?php

use App\Models\SystemSetting;
use App\Models\User;
use Database\Seeders\SystemSettingSeeder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Storage::fake('public');
    Cache::forget('system-settings.global');
});

test('the website name, logo and favicon come from the database', function () {
    SystemSetting::query()->create(['key' => 'app_name', 'value' => 'Portal Uji Coba']);
    SystemSetting::query()->create(['key' => 'logo_path', 'value' => 'branding/logo-uji.png']);
    SystemSetting::query()->create(['key' => 'favicon_path', 'value' => 'branding/favicon-uji.ico']);

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('name', 'Portal Uji Coba')
            ->where('branding.name', 'Portal Uji Coba')
            ->where('branding.logoUrl', Storage::disk('public')->url('branding/logo-uji.png'))
            ->where('branding.faviconUrl', Storage::disk('public')->url('branding/favicon-uji.ico')));
});

test('the page title and favicon in the html are rendered from the database', function () {
    SystemSetting::query()->create(['key' => 'app_name', 'value' => 'Portal Uji Coba']);
    SystemSetting::query()->create(['key' => 'favicon_path', 'value' => 'branding/favicon-uji.ico']);

    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertSee('Portal Uji Coba</title>', false)
        ->assertSee(Storage::disk('public')->url('branding/favicon-uji.ico'), false)
        ->assertDontSee('/favicon.ico', false);
});

test('without a stored logo or favicon no hardcoded image is offered', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('branding.logoUrl', null)->where('branding.faviconUrl', null))
        ->assertDontSee('rel="icon"', false);
});

test('the seeder gives a fresh install a name, logo and favicon stored on the public disk', function () {
    $this->seed(SystemSettingSeeder::class);

    $settings = SystemSetting::query()->pluck('value', 'key');

    expect($settings['app_name'])->toBe('Portal QCC')
        ->and($settings['logo_path'])->toBe('branding/logo.png')
        ->and($settings['favicon_path'])->toBe('branding/favicon.png');
    Storage::disk('public')->assertExists(['branding/logo.png', 'branding/favicon.png']);
});

test('the seeder never overwrites values an admin already saved', function () {
    SystemSetting::query()->create(['key' => 'app_name', 'value' => 'Nama Pilihan Admin']);
    SystemSetting::query()->create(['key' => 'logo_path', 'value' => 'system-settings/logo-admin.png']);

    $this->seed(SystemSettingSeeder::class);
    $this->seed(SystemSettingSeeder::class);

    $settings = SystemSetting::query()->pluck('value', 'key');

    expect($settings['app_name'])->toBe('Nama Pilihan Admin')
        ->and($settings['logo_path'])->toBe('system-settings/logo-admin.png')
        ->and(SystemSetting::query()->where('key', 'app_name')->count())->toBe(1);
    Storage::disk('public')->assertMissing('branding/logo.png');
});
