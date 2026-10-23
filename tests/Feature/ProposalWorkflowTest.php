<?php

use App\Enums\ProposalStatus;
use App\Enums\TeamMatchingStatus;
use App\Models\AuditLog;
use App\Models\Organization;
use App\Models\Project;
use App\Models\QccBatch;
use App\Models\ReviewDecision;
use App\Models\TeamMatchingRun;
use App\Models\User;
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
        'background' => 'Latar belakang',
        'problem_statement' => 'Masalah utama',
        'current_situation' => 'Kondisi saat ini',
        'baseline' => 'Baseline 10 jam',
        'target' => 'Target 5 jam',
        'scope' => 'Proses internal',
        'learning_goals_json' => ['Belajar A', 'Belajar B', 'Belajar C'],
        'rca' => 'Analisis akar masalah',
        'improvement_plan' => 'Rencana perbaikan',
        'business_benefit' => 'Hemat waktu',
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

    $this->actingAs($reviewer)->post(route('proposals.review.start', $project))->assertRedirect();
    $this->post(route('proposals.review.decide', $project), [
        'decision' => 'REQUEST_REVISION',
        'reason' => 'Perjelas baseline pengukuran.',
    ])->assertRedirect();

    expect($project->fresh()->proposal_status)->toBe(ProposalStatus::RevisionRequired)
        ->and(ReviewDecision::query()->where('project_id', $project->id)->latest('id')->value('reason'))->toBe('Perjelas baseline pengukuran.')
        ->and(AuditLog::query()->where('event', 'proposal.request.revision')->where('reason', 'Perjelas baseline pengukuran.')->exists())->toBeTrue();

    $this->actingAs($proposer)->post(route('proposals.submit', $project))->assertRedirect();
    expect(ReviewDecision::query()->where('project_id', $project->id)->where('decision', 'RESUBMITTED')->exists())->toBeTrue();

    $this->actingAs($reviewer)->post(route('proposals.review.start', $project))->assertRedirect();
    $this->post(route('proposals.review.decide', $project), ['decision' => 'APPROVE'])->assertRedirect();

    expect($project->fresh()->proposal_status)->toBe(ProposalStatus::Approved)
        ->and($project->fresh()->steps)->toHaveCount(8)
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

test('proposal submission blocks missing content and an unconfirmed team', function () {
    [$project, $proposer] = preparedProposal();
    $project->forceFill(['baseline' => null])->save();
    TeamMatchingRun::query()->where('project_id', $project->id)->update(['confirmed_at' => null]);

    $this->actingAs($proposer)
        ->post(route('proposals.submit', $project))
        ->assertSessionHasErrors('baseline');

    $project->forceFill(['baseline' => 'Baseline 10 jam'])->save();
    $this->post(route('proposals.submit', $project))->assertSessionHasErrors('team');

    expect($project->fresh()->proposal_status)->toBe(ProposalStatus::Draft)
        ->and(ReviewDecision::query()->where('project_id', $project->id)->count())->toBe(0);
});
