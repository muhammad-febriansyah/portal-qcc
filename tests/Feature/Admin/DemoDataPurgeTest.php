<?php

use App\Enums\ProposalStatus;
use App\Models\Project;
use App\Models\QccBatch;
use App\Models\ReviewDecision;
use App\Models\User;
use App\Notifications\ProposalWorkflowNotification;
use Database\Seeders\AdminPermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    $this->seed(AdminPermissionSeeder::class);
});

function superAdmin(string $email = 'admin@sim.co.id'): User
{
    $admin = User::factory()->create(['email' => $email]);
    $admin->assignRole('Super Admin');

    return $admin;
}

test('a Super Admin removes demo accounts and demo data but keeps real ones', function () {
    $admin = superAdmin();
    $demoBatch = QccBatch::factory()->create(['code' => 'DEMO-QCC-2026-01']);
    $realBatch = QccBatch::factory()->create(['code' => 'BATCH-4']);
    $demoMentor = User::factory()->create(['email' => 'demo.mentor.01@example.test']);
    $demoMentor->assignRole(Role::findOrCreate('Mentor', 'web'));
    $demoProposer = User::factory()->create(['email' => 'demo.simer.01@example.test']);
    $realUser = User::factory()->create(['email' => 'gilang.ramadhan@sim.co.id']);

    $demoProject = Project::factory()->create(['batch_id' => $realBatch->id, 'proposer_id' => $demoProposer->id]);
    $projectInDemoBatch = Project::factory()->create(['batch_id' => $demoBatch->id, 'proposer_id' => $realUser->id]);
    $realProject = Project::factory()->create(['batch_id' => $realBatch->id, 'proposer_id' => $realUser->id]);
    ReviewDecision::query()->create([
        'project_id' => $demoProject->id, 'actor_id' => $demoMentor->id, 'decision' => 'APPROVE',
        'previous_status' => ProposalStatus::UnderReview->value, 'new_status' => ProposalStatus::Approved->value, 'decided_at' => now(),
    ]);
    $demoMentor->notify(new ProposalWorkflowNotification($demoProject->id, 'Judul', 'Isi', '/proposals', 'SUBMITTED'));

    $this->actingAs($admin)
        ->get(route('admin.settings.index'))
        ->assertInertia(fn (Assert $page) => $page->where('demoData', ['users' => 2, 'projects' => 2, 'batches' => 1]));

    $this->delete(route('admin.settings.demo-data.destroy'), ['confirmation' => 'HAPUS'])
        ->assertRedirect(route('admin.settings.index'));

    expect(User::query()->whereKey([$demoMentor->id, $demoProposer->id])->exists())->toBeFalse()
        ->and(Project::query()->whereKey([$demoProject->id, $projectInDemoBatch->id])->exists())->toBeFalse()
        ->and(QccBatch::query()->whereKey($demoBatch->id)->exists())->toBeFalse()
        ->and($realProject->fresh())->not->toBeNull()
        ->and($realUser->fresh())->not->toBeNull()
        ->and($realBatch->fresh())->not->toBeNull()
        ->and($admin->fresh())->not->toBeNull();
    $this->assertDatabaseMissing('notifications', ['notifiable_id' => $demoMentor->id]);
    $this->assertDatabaseMissing('model_has_roles', ['model_id' => $demoMentor->id]);
    $this->assertDatabaseHas('audit_logs', ['event' => 'system_setting.demo_data_purged', 'actor_id' => $admin->id]);
});

test('the Super Admin running the purge keeps their own demo account', function () {
    $admin = superAdmin('demo.super-admin.01@example.test');

    $this->actingAs($admin)
        ->delete(route('admin.settings.demo-data.destroy'), ['confirmation' => 'HAPUS'])
        ->assertRedirect();

    expect($admin->fresh())->not->toBeNull();
});

test('a demo account still used by real data is deactivated instead of deleted', function () {
    $admin = superAdmin();
    $demoReviewer = User::factory()->create(['email' => 'demo.owner-qcc.01@example.test']);
    $realProject = Project::factory()->create(['proposer_id' => User::factory()->create()->id]);
    ReviewDecision::query()->create([
        'project_id' => $realProject->id, 'actor_id' => $demoReviewer->id, 'decision' => 'REVIEW_STARTED',
        'previous_status' => ProposalStatus::Submitted->value, 'new_status' => ProposalStatus::UnderReview->value, 'decided_at' => now(),
    ]);

    $this->actingAs($admin)
        ->delete(route('admin.settings.demo-data.destroy'), ['confirmation' => 'HAPUS'])
        ->assertRedirect();

    expect($demoReviewer->fresh()?->is_active)->toBeFalse()
        ->and($realProject->fresh())->not->toBeNull();
});

test('the purge needs the typed confirmation', function () {
    $demo = User::factory()->create(['email' => 'demo.simer.01@example.test']);

    $this->actingAs(superAdmin())
        ->delete(route('admin.settings.demo-data.destroy'), ['confirmation' => 'hapus'])
        ->assertSessionHasErrors('confirmation');

    expect($demo->fresh())->not->toBeNull();
});

test('only a Super Admin may see or run the purge', function () {
    $owner = User::factory()->create();
    $owner->givePermissionTo('system-settings.view', 'system-settings.manage');
    $demo = User::factory()->create(['email' => 'demo.simer.01@example.test']);

    $this->actingAs($owner)
        ->get(route('admin.settings.index'))
        ->assertInertia(fn (Assert $page) => $page->where('demoData', null));

    $this->delete(route('admin.settings.demo-data.destroy'), ['confirmation' => 'HAPUS'])->assertForbidden();

    expect($demo->fresh())->not->toBeNull();
});
