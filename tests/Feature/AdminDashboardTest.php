<?php

use App\Enums\BatchStatus;
use App\Models\AuditLog;
use App\Models\Competency;
use App\Models\MemberCompetency;
use App\Models\QccBatch;
use App\Models\User;
use App\Services\AdminDashboardOverview;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    Cache::forget(AdminDashboardOverview::CACHE_KEY);
});

function dashboardAdmin(): User
{
    $user = User::factory()->create(['name' => 'Rina Admin']);
    $user->assignRole(Role::findOrCreate('Super Admin', 'web'));
    $user->givePermissionTo(Permission::findOrCreate('dashboard.admin', 'web'));

    return $user;
}

function clearAuditLogs(): void
{
    AuditLog::query()->delete();
    Cache::forget(AdminDashboardOverview::CACHE_KEY);
}

function logEvent(string $event, int $daysAgo = 0, ?int $actorId = null): AuditLog
{
    return AuditLog::factory()->create([
        'event' => $event,
        'actor_id' => $actorId,
        'occurred_at' => now()->subDays($daysAgo)->setTime(10, 0),
    ]);
}

test('people without the dashboard permission get no admin overview', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('dashboard')->where('adminOverview', null));
});

test('the super admin overview has cards, a 30 day series and recent activity', function () {
    $this->actingAs(dashboardAdmin())
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('adminOverview.cards')
            ->has('adminOverview.activity.days', 30)
            ->has('adminOverview.activity.login', 30)
            ->has('adminOverview.activity.changes', 30)
            ->has('adminOverview.activity.failed', 30)
            ->has('adminOverview.recent'));
});

test('the cards count members, participants who filled in competencies and batches', function () {
    $admin = dashboardAdmin();
    $filled = User::factory()->create();
    $filled->assignRole(Role::findOrCreate('Team Leader', 'web'));
    $empty = User::factory()->create();
    $empty->assignRole('Team Leader');
    $off = User::factory()->create();
    $off->forceFill(['is_active' => false])->save();
    MemberCompetency::factory()->create(['user_id' => $filled->id, 'competency_id' => Competency::factory()->create()->id]);
    QccBatch::factory()->active()->create();
    QccBatch::factory()->create(['status' => BatchStatus::Draft]);
    QccBatch::factory()->closed()->create();

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('adminOverview.cards.members_active', 3)
            ->where('adminOverview.cards.members_inactive', 1)
            ->where('adminOverview.cards.participants', 2)
            ->where('adminOverview.cards.participants_filled', 1)
            ->where('adminOverview.cards.batches_active', 1)
            ->where('adminOverview.cards.batches_total', 3));
});

test('the series counts logins, data changes and failed logins per day and fills empty days with zero', function () {
    $admin = dashboardAdmin();
    clearAuditLogs();
    logEvent('auth.login', 0);
    logEvent('auth.login', 0);
    logEvent('auth.login_failed', 0);
    logEvent('batch.created', 0);
    logEvent('user.updated', 0);
    logEvent('auth.logout', 0);
    logEvent('auth.login', 5);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('adminOverview.activity.days.29', now()->toDateString())
            ->where('adminOverview.activity.login.29', 2)
            ->where('adminOverview.activity.failed.29', 1)
            ->where('adminOverview.activity.changes.29', 2)
            ->where('adminOverview.activity.login.24', 1)
            ->where('adminOverview.activity.login.20', 0));
});

test('activity older than 30 days is left out of the series', function () {
    $admin = dashboardAdmin();
    clearAuditLogs();
    logEvent('auth.login', 45);
    logEvent('auth.login', 29);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('adminOverview.activity.days.0', now()->subDays(29)->toDateString())
            ->where('adminOverview.activity.login.0', 1)
            ->where('adminOverview.activity.login', fn ($values) => collect($values)->sum() === 1));
});

test('the week card compares with the week before', function () {
    $admin = dashboardAdmin();
    clearAuditLogs();
    logEvent('batch.created', 1);
    logEvent('batch.created', 2);
    logEvent('batch.created', 3);
    logEvent('batch.created', 9);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('adminOverview.cards.activity_week', 3)
            ->where('adminOverview.cards.activity_previous_week', 1));
});

test('recent activity lists the newest eight entries as plain sentences', function () {
    $admin = dashboardAdmin();
    $actor = User::factory()->create(['name' => 'Budi Pelaku']);
    clearAuditLogs();

    foreach (range(1, 10) as $number) {
        AuditLog::factory()->create([
            'event' => 'auth.login',
            'actor_id' => $actor->id,
            'occurred_at' => now()->subMinutes(20 - $number),
        ]);
    }

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('adminOverview.recent', 8)
            ->where('adminOverview.recent.0.description', fn (string $text) => str_starts_with($text, 'Budi Pelaku masuk ke '))
            ->where('adminOverview.recent.0.module', 'Autentikasi'));
});

test('the overview is kept briefly instead of being rebuilt on every visit', function () {
    $admin = dashboardAdmin();
    clearAuditLogs();

    $this->actingAs($admin)->get(route('dashboard'));
    logEvent('batch.created', 0);

    $this->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('adminOverview.cards.activity_week', 0));

    Cache::forget(AdminDashboardOverview::CACHE_KEY);

    $this->get(route('dashboard'))
        ->assertInertia(fn (Assert $page) => $page->where('adminOverview.cards.activity_week', 1));
});

test('the overview never exposes secrets or other personal data', function () {
    $admin = dashboardAdmin();
    clearAuditLogs();
    AuditLog::factory()->create(['event' => 'user.updated', 'before_json' => ['email' => 'rahasia@example.com'], 'after_json' => ['email' => 'baru@example.com']]);

    $html = $this->actingAs($admin)->get(route('dashboard'))->getContent();

    expect($html)->not->toContain('rahasia@example.com')
        ->and($html)->not->toContain('password');
});

test('building the overview takes a small, fixed number of queries', function () {
    $admin = dashboardAdmin();
    clearAuditLogs();

    foreach (range(1, 12) as $number) {
        logEvent('auth.login', $number % 5);
    }

    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });

    app(AdminDashboardOverview::class)->get();

    expect($queries)->toBeLessThan(20);
});
