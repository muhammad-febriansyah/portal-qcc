<?php

use App\Models\User;
use App\Notifications\ProjectActivityNotification;
use App\Support\NotificationKinds;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;

function notificationRecord(User $user, string $decision, int $projectId = 12, bool $read = false): string
{
    $id = (string) Str::uuid();

    $user->notifications()->create([
        'id' => $id,
        'type' => ProjectActivityNotification::class,
        'data' => [
            'project_id' => $projectId,
            'title' => "Pemberitahuan {$decision}",
            'message' => "Pesan untuk {$decision}.",
            'url' => route('dashboard'),
            'decision' => $decision,
        ],
        'read_at' => $read ? now() : null,
    ]);

    return $id;
}

function notificationUser(): User
{
    return User::factory()->create(['needs_profile_setup' => false]);
}

it('maps notification decisions to groups and uses generic metadata for unknown decisions', function (string $decision, string $kind, string $label): void {
    expect(NotificationKinds::describe($decision))
        ->toMatchArray(['kind' => $kind, 'kind_label' => $label]);
})->with([
    ['SUBMITTED', 'proposal', 'Proposal'],
    ['STEP_APPROVED', 'step_qcc', 'Step QCC'],
    ['BIWEEKLY_SUBMITTED', 'biweekly', 'Bi-weekly'],
    ['MENTOR_ASSIGNED', 'mentoring', 'Mentoring'],
    ['ACTION_ASSIGNED', 'risk_action', 'Risiko & Tindakan'],
    ['DUE_SOON_3', 'risk_action', 'Risiko & Tindakan'],
    ['SCORING_COMPLETED', 'ai_scoring', 'AI Scoring'],
    ['SOMETHING_NEW', 'other', 'Notifikasi'],
]);

it('marks only the authenticated user notification as read', function () {
    $user = notificationUser();
    $other = notificationUser();
    $ownId = notificationRecord($user, 'SUBMITTED');
    $otherId = notificationRecord($other, 'REJECT');

    $this->actingAs($user)->post(route('notifications.read', $ownId))->assertRedirect();
    expect($user->notifications()->findOrFail($ownId)->read_at)->not->toBeNull()
        ->and($other->notifications()->findOrFail($otherId)->read_at)->toBeNull();

    $this->actingAs($user)->post(route('notifications.read', $otherId))->assertNotFound();
});

it('marks all and opens notifications only for the authenticated user', function () {
    $user = notificationUser();
    $other = notificationUser();
    $ownId = notificationRecord($user, 'SUBMITTED');
    $otherId = notificationRecord($other, 'STEP_SUBMITTED');

    $this->actingAs($user)->post(route('notifications.read-all'))->assertRedirect();
    expect($user->unreadNotifications()->count())->toBe(0)
        ->and($other->unreadNotifications()->count())->toBe(1);

    $this->actingAs($user)->get(route('notifications.open', $otherId))->assertNotFound();
    $this->actingAs($other)->get(route('notifications.open', $otherId))->assertRedirect(route('proposals.show', 12));
    expect($user->notifications()->findOrFail($ownId)->read_at)->not->toBeNull()
        ->and($other->notifications()->findOrFail($otherId)->read_at)->not->toBeNull();
});

it('filters unread notifications and groups and paginates only the users own notifications', function () {
    $user = notificationUser();
    $other = notificationUser();

    foreach (range(1, 23) as $number) {
        notificationRecord($user, $number % 2 === 0 ? 'STEP_SUBMITTED' : 'SUBMITTED', $number, read: $number > 20);
    }
    notificationRecord($other, 'SUBMITTED', 99);

    $this->actingAs($user)->get(route('notifications.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('notifications/index')
            ->where('items.total', 23)
            ->where('items.per_page', 20)
            ->has('items.data', 20)
            ->where('notifications.unread_count', 20));

    $this->actingAs($user)->get(route('notifications.index', ['status' => 'unread', 'kind' => 'step_qcc']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('filters.status', 'unread')
            ->where('filters.kind', 'step_qcc')
            ->where('items.total', 10)
            ->has('items.data', 10)
            ->where('items.data.0.kind', 'step_qcc'));

    $this->actingAs($user)->get(route('notifications.index', ['page' => 2]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('items.current_page', 2)
            ->where('items.total', 23)
            ->has('items.data', 3));
});
