<?php

use App\Enums\ProposalStatus;
use App\Enums\QccStep;
use App\Enums\TeamMatchingStatus;
use App\Enums\ThemeCategory;
use App\Models\AuditLog;
use App\Models\Competency;
use App\Models\Organization;
use App\Models\Project;
use App\Models\QccBatch;
use App\Models\ReviewDecision;
use App\Models\TeamMatchingRun;
use App\Models\User;
use App\Services\Teams\CandidateShortlist;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

/** @return array{Project, User, User} */
function preparedProposal(): array
{
    $firstOrganization = Organization::factory()->create(['is_active' => true]);
    $secondOrganization = Organization::factory()->create(['is_active' => true]);
    $proposer = User::factory()->create(['organization_id' => $firstOrganization->id]);
    $proposer->assignRole(Role::findOrCreate('Team Leader', 'web'));
    $proposer->givePermissionTo([
        Permission::findOrCreate('projects.view', 'web'),
        Permission::findOrCreate('projects.create', 'web'),
        Permission::findOrCreate('projects.update', 'web'),
        Permission::findOrCreate('projects.submit', 'web'),
    ]);
    $member = User::factory()->create(['organization_id' => $secondOrganization->id]);
    $batch = QccBatch::factory()->active()->create();
    $project = Project::factory()->create([
        'batch_id' => $batch->id,
        'proposer_id' => $proposer->id,
        'title' => 'Kurangi waktu proses',
        'theme' => 'Efisiensi',
        'theme_category' => ThemeCategory::CostEfficiency,
        'background' => 'Latar belakang',
        'current_situation' => 'Kondisi saat ini, baseline 10 jam',
        'target' => 'Target 5 jam',
        'scope' => 'Proses internal',
        'learning_goals_json' => ['Belajar A', 'Belajar B', 'Belajar C'],
        'improvement_plan' => 'Rencana perbaikan',
        'cost_projection' => 'Tidak ada biaya',
        'step_timeline_json' => array_map(fn (QccStep $step): array => [
            'step_number' => $step->value,
            'start_date' => now()->addWeeks($step->value - 1)->toDateString(),
            'target_date' => now()->addWeeks($step->value)->toDateString(),
        ], QccStep::cases()),
    ]);
    DB::table('project_requirements')->insert([
        'project_id' => $project->id,
        'competency_id' => Competency::factory()->create()->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('project_members')->insert([
        [
            'project_id' => $project->id,
            'user_id' => $proposer->id,
            'role_in_project' => 'LEADER',
            'status' => 'ACTIVE',
            'joined_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'project_id' => $project->id,
            'user_id' => $member->id,
            'role_in_project' => 'MEMBER',
            'status' => 'ACTIVE',
            'joined_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    $matchingRun = TeamMatchingRun::query()->create([
        'project_id' => $project->id,
        'requested_by' => $proposer->id,
        'status' => TeamMatchingStatus::Ready,
        'idempotency_key' => (string) str()->uuid(),
        'candidate_snapshot_json' => app(CandidateShortlist::class)->snapshot($project, app(CandidateShortlist::class)->for($project)),
    ]);
    $matchingRun->forceFill(['confirmed_by' => $proposer->id, 'confirmed_at' => now()])->save();

    $reviewer = User::factory()->create();
    $reviewer->assignRole(Role::findOrCreate('Owner QCC', 'web'));
    $reviewer->givePermissionTo([
        Permission::findOrCreate('projects.view', 'web'),
        Permission::findOrCreate('projects.viewAny', 'web'),
        Permission::findOrCreate('projects.review', 'web'),
        Permission::findOrCreate('projects.approve', 'web'),
    ]);

    return [$project, $proposer, $reviewer];
}

test('proposal can be submitted revised resubmitted and approved with visible history', function () {
    [$project, $proposer, $reviewer] = preparedProposal();

    $this->actingAs($proposer)->post(route('proposals.submit', $project))->assertRedirect();
    expect($project->fresh()->proposal_status)->toBe(ProposalStatus::Submitted);
    $notices = fn (User $user, string $kind): int => $user->notifications()->where('data->decision', $kind)->count();
    expect($notices($reviewer, 'SUBMITTED'))->toBe(1);

    $this->actingAs($reviewer)->post(route('proposals.review.start', $project))->assertRedirect();
    $this->post(route('proposals.review.decide', $project), [
        'decision' => 'REQUEST_REVISION',
        'reason' => 'Perjelas baseline pengukuran.',
    ])->assertRedirect();

    expect($project->fresh()->proposal_status)->toBe(ProposalStatus::RevisionRequired)
        ->and($notices($proposer, 'REQUEST_REVISION'))->toBe(1)
        ->and(ReviewDecision::query()->where('project_id', $project->id)->latest('id')->value('reason'))->toBe('Perjelas baseline pengukuran.')
        ->and(AuditLog::query()->where('event', 'proposal.request.revision')->where('reason', 'Perjelas baseline pengukuran.')->exists())->toBeTrue();

    $this->actingAs($proposer)->post(route('proposals.submit', $project))->assertRedirect();
    expect(ReviewDecision::query()->where('project_id', $project->id)->where('decision', 'RESUBMITTED')->exists())->toBeTrue();
    expect($notices($reviewer, 'RESUBMITTED'))->toBe(1);

    $this->actingAs($reviewer)->post(route('proposals.review.start', $project))->assertRedirect();
    $this->post(route('proposals.review.decide', $project), ['decision' => 'APPROVE'])->assertRedirect();

    expect($project->fresh()->proposal_status)->toBe(ProposalStatus::Approved)
        ->and($notices($proposer, 'APPROVE'))->toBe(1)
        ->and($proposer->notifications()->where('data->decision', 'APPROVE')->value('data')['message'])->toContain('lolos seleksi. Owner QCC akan menyusun tim proyeknya.')
        ->and($proposer->notifications()->where('data->decision', 'REQUEST_REVISION')->value('data')['message'])->toContain('Catatan reviewer: Perjelas baseline pengukuran.')
        ->and($project->fresh()->steps)->toHaveCount(8)
        ->and($project->fresh()->steps->first()->target_date?->toDateString())->toBe(now()->addWeek()->toDateString())
        ->and(ReviewDecision::query()->where('project_id', $project->id)->count())->toBe(6);

    $this->actingAs($proposer)
        ->get(route('proposals.show', $project))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('proposal.status', ProposalStatus::Approved->value)
            ->where('proposal.review_history.0.decision', 'APPROVE')
            ->where('proposal.review_history.3.reason', 'Perjelas baseline pengukuran.'));
});

test('proposal transitions reject missing review reasons and invalid state changes', function () {
    [$project, $proposer, $reviewer] = preparedProposal();

    $this->actingAs($proposer)->post(route('proposals.submit', $project))->assertRedirect();
    $this->actingAs($reviewer)->post(route('proposals.review.start', $project))->assertRedirect();

    $this->post(route('proposals.review.decide', $project), ['decision' => 'REJECT'])
        ->assertSessionHasErrors('reason');

    $this->post(route('proposals.review.start', $project))->assertSessionHasErrors('proposal');

    expect($project->fresh()->proposal_status)->toBe(ProposalStatus::UnderReview)
        ->and(ReviewDecision::query()->where('project_id', $project->id)->count())->toBe(2);
});

test('an active team leader cannot change a proposal once it is submitted', function () {
    [$project, $proposer] = preparedProposal();

    expect($proposer->can('update', $project))->toBeTrue();

    $project->forceFill(['proposal_status' => ProposalStatus::Submitted])->save();

    expect($proposer->fresh()->can('update', $project->fresh()))->toBeFalse();
});

test('proposal submission blocks a timeline without start dates', function () {
    [$project, $proposer] = preparedProposal();
    $project->forceFill(['step_timeline_json' => array_map(fn (QccStep $step): array => [
        'step_number' => $step->value,
        'target_date' => now()->addWeeks($step->value)->toDateString(),
    ], QccStep::cases())])->save();

    $this->actingAs($proposer)
        ->post(route('proposals.submit', $project))
        ->assertSessionHasErrors(['step_timeline' => 'Isi tanggal mulai dan target selesai semua step QCC sebelum proposal diajukan.']);

    expect($project->fresh()->proposal_status)->toBe(ProposalStatus::Draft);
});

test('proposal submission blocks a missing timeline and competency needs but not a missing cost projection', function () {
    [$project, $proposer] = preparedProposal();
    $project->forceFill(['cost_projection' => null, 'step_timeline_json' => null])->save();
    DB::table('project_requirements')->where('project_id', $project->id)->delete();

    $this->actingAs($proposer)
        ->post(route('proposals.submit', $project))
        ->assertSessionHasErrors(['step_timeline', 'requirements'])
        ->assertSessionDoesntHaveErrors(['cost_projection']);

    expect($project->fresh()->proposal_status)->toBe(ProposalStatus::Draft)
        ->and(ReviewDecision::query()->where('project_id', $project->id)->count())->toBe(0);
});

test('a proposal is decided without a team and the team is formed only after it passes', function () {
    [$project, $proposer, $reviewer] = preparedProposal();
    DB::table('project_members')->where('project_id', $project->id)->delete();
    TeamMatchingRun::query()->where('project_id', $project->id)->delete();

    $this->actingAs($proposer)->post(route('proposals.submit', $project))->assertSessionHasNoErrors();
    $this->actingAs($reviewer)->post(route('proposals.review.start', $project))->assertRedirect();

    $this->get(route('proposals.show', $project))
        ->assertInertia(fn (Assert $page) => $page->where('proposal.can_find_candidates', false));
    $this->get(route('proposals.candidates', $project))->assertRedirect(route('proposals.show', $project));

    $this->post(route('proposals.review.decide', $project), ['decision' => 'APPROVE'])->assertSessionHasNoErrors();

    expect($project->fresh()->proposal_status)->toBe(ProposalStatus::Approved);

    $this->get(route('proposals.show', $project))
        ->assertInertia(fn (Assert $page) => $page
            ->where('proposal.can_find_candidates', true)
            ->where('proposal.has_team', false));
    $this->get(route('proposals.candidates', $project))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('proposals/candidates')
            ->where('can_edit_team', true)
            ->where('can_manage_approved_team', false));
});

test('the proposer is told when the proposal is rejected', function () {
    [$project, $proposer, $reviewer] = preparedProposal();

    $this->actingAs($proposer)->post(route('proposals.submit', $project))->assertSessionHasNoErrors();
    $this->actingAs($reviewer)->post(route('proposals.review.start', $project))->assertRedirect();
    $this->post(route('proposals.review.decide', $project), ['decision' => 'REJECT', 'reason' => 'Di luar ruang lingkup batch.'])
        ->assertSessionHasNoErrors();

    expect($project->fresh()->proposal_status)->toBe(ProposalStatus::Rejected)
        ->and($proposer->notifications()->where('data->decision', 'REJECT')->value('data')['message'])->toContain('Catatan reviewer: Di luar ruang lingkup batch.');
});
