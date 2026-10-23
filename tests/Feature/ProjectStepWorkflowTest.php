<?php

use App\Actions\Proposals\InitializeProjectSteps;
use App\Enums\ProposalStatus;
use App\Enums\StepStatus;
use App\Models\AuditLog;
use App\Models\Project;
use App\Models\ProjectStep;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function stepPerson(string $role, array $permissions): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate($role, 'web'));
    $user->givePermissionTo(array_map(fn (string $name) => Permission::findOrCreate($name, 'web'), $permissions));

    return $user;
}

function stepTeamMember(Project $project, string $role = 'Team Leader'): User
{
    $user = stepPerson($role, ['projects.view', 'projects.update', 'projects.submit']);
    DB::table('project_members')->insert([
        'project_id' => $project->id, 'user_id' => $user->id, 'role_in_project' => $role === 'Team Leader' ? 'LEADER' : 'MEMBER',
        'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now(),
    ]);

    return $user;
}

function stepReviewer(bool $canApprove = true): User
{
    return stepPerson('Owner QCC', array_filter(['projects.view', 'projects.viewAny', 'projects.review', $canApprove ? 'projects.approve' : null]));
}

function stepPayload(ProjectStep $step, array $overrides = []): array
{
    return [
        'activity_description' => 'Kumpulkan data keluhan',
        'plan_text' => 'Survei dua minggu',
        'actual_text' => '',
        'target_date' => '2026-12-01',
        'progress_pct' => 30,
        'submitter_note' => '',
        'lock_version' => $step->fresh()->lock_version,
        ...$overrides,
    ];
}

beforeEach(function () {
    $this->project = Project::factory()->status(ProposalStatus::Approved)->create();
    app(InitializeProjectSteps::class)->handle($this->project);
    $this->step = ProjectStep::query()->where('project_id', $this->project->id)->where('step_number', 1)->first();
    $this->leader = stepTeamMember($this->project);
    $this->reviewer = stepReviewer();
    $this->url = fn (string $name = 'update') => route('projects.steps.'.$name, [$this->project, 1]);
});

it('saves a draft, starts the step and keeps a history entry with the content', function () {
    $this->actingAs($this->leader)->patch(($this->url)(), stepPayload($this->step))
        ->assertRedirect()->assertSessionHasNoErrors();

    $step = $this->step->fresh();
    $history = $step->histories()->first();

    expect($step->status)->toBe(StepStatus::InProgress)
        ->and($step->activity_description)->toBe('Kumpulkan data keluhan')
        ->and($step->progress_pct)->toBe(30)
        ->and($step->target_date->toDateString())->toBe('2026-12-01')
        ->and($step->lock_version)->toBe(1)
        ->and($history->action)->toBe('SAVED')->and($history->version)->toBe(1)
        ->and($history->snapshot_json['plan_text'])->toBe('Survei dua minggu')
        ->and(AuditLog::query()->where('event', 'step.saved')->count())->toBe(1);
});

it('does not write history when a save changes nothing', function () {
    $this->actingAs($this->leader)->patch(($this->url)(), stepPayload($this->step));
    $this->actingAs($this->leader)->patch(($this->url)(), stepPayload($this->step));

    expect($this->step->fresh()->lock_version)->toBe(1)->and($this->step->histories()->count())->toBe(1);
});

it('refuses a save made from an out of date page', function () {
    $this->actingAs($this->leader)->patch(($this->url)(), stepPayload($this->step));

    $this->actingAs($this->leader)->patch(($this->url)(), stepPayload($this->step, ['lock_version' => 0, 'actual_text' => 'timpa']))
        ->assertSessionHasErrors('lock_version');

    expect($this->step->fresh()->actual_text)->toBeNull();
});

it('validates the fields', function () {
    foreach ([['progress_pct' => 101], ['progress_pct' => -1], ['target_date' => 'besok'], ['plan_text' => str_repeat('a', 20001)]] as $bad) {
        $this->actingAs($this->leader)->patch(($this->url)(), stepPayload($this->step, $bad))->assertSessionHasErrors(array_key_first($bad));
    }
});

it('needs the activity and the plan before a step can be submitted', function () {
    $this->actingAs($this->leader)->post(($this->url)('submit'), stepPayload($this->step, ['activity_description' => '', 'plan_text' => '']))
        ->assertSessionHasErrors(['activity_description', 'plan_text']);

    expect($this->step->fresh()->status)->toBe(StepStatus::NotStarted);
});

it('walks submit, review, revision, resubmit and approval, locking the content on the way', function () {
    $actingLeader = fn () => $this->actingAs($this->leader);

    $actingLeader()->post(($this->url)('submit'), stepPayload($this->step))->assertSessionHasNoErrors();
    expect($this->step->fresh()->status)->toBe(StepStatus::Submitted)
        ->and($this->step->fresh()->submitted_at)->not->toBeNull()
        ->and($this->reviewer->notifications()->count())->toBe(1)
        ->and($this->leader->notifications()->count())->toBe(0);

    $actingLeader()->patch(($this->url)(), stepPayload($this->step, ['actual_text' => 'ubah']))->assertSessionHasErrors('step');

    $this->actingAs($this->reviewer)->post(($this->url)('review.start'))->assertSessionHasNoErrors();
    expect($this->step->fresh()->status)->toBe(StepStatus::UnderReview);

    $this->actingAs($this->reviewer)->post(($this->url)('revision'), ['reason' => ''])->assertSessionHasErrors('reason');
    $this->actingAs($this->reviewer)->post(($this->url)('revision'), ['reason' => 'Data survei kurang'])->assertSessionHasNoErrors();
    expect($this->step->fresh()->status)->toBe(StepStatus::RevisionRequired);
    expect($this->leader->notifications()->count())->toBe(1);

    $actingLeader()->patch(($this->url)(), stepPayload($this->step, ['actual_text' => 'Tambah responden']))->assertSessionHasNoErrors();
    expect($this->step->fresh()->status)->toBe(StepStatus::InProgress);

    $actingLeader()->post(($this->url)('submit'), stepPayload($this->step, ['actual_text' => 'Tambah responden']))->assertSessionHasNoErrors();
    $this->actingAs($this->reviewer)->post(($this->url)('review.start'));
    $this->actingAs($this->reviewer)->post(($this->url)('approve'))->assertSessionHasNoErrors();

    $step = $this->step->fresh();

    expect($step->status)->toBe(StepStatus::Approved)->and($step->progress_pct)->toBe(100)->and($step->approved_at)->not->toBeNull();
    expect($this->leader->notifications()->count())->toBe(2)
        ->and($this->reviewer->notifications()->count())->toBe(2);

    $actingLeader()->patch(($this->url)(), stepPayload($this->step, ['actual_text' => 'setelah approve']))->assertSessionHasErrors('step');

    expect($this->step->fresh()->actual_text)->toBe('Tambah responden')
        ->and($step->histories()->pluck('action')->reverse()->values()->all())->toBe(['SUBMITTED', 'REVIEW_STARTED', 'REVISION_REQUESTED', 'SAVED', 'SUBMITTED', 'REVIEW_STARTED', 'APPROVED'])
        ->and($step->histories()->where('action', 'REVISION_REQUESTED')->first()->reason)->toBe('Data survei kurang');
});

it('writes an audit entry for every move', function () {
    $this->actingAs($this->leader)->post(($this->url)('submit'), stepPayload($this->step));
    $this->actingAs($this->reviewer)->post(($this->url)('review.start'));
    $this->actingAs($this->reviewer)->post(($this->url)('approve'));

    $events = AuditLog::query()->where('event', 'like', 'step.%')->orderBy('id')->pluck('event')->all();

    expect($events)->toBe(['step.submitted', 'step.review_started', 'step.approved'])
        ->and(AuditLog::query()->where('event', 'step.approved')->first())
        ->actor_id->toBe($this->reviewer->id)
        ->subject_label->toContain('Step 1 (Menentukan Tema Improvement)');
});

it('does not skip states', function () {
    $this->actingAs($this->reviewer)->post(($this->url)('review.start'))->assertSessionHasErrors('step');
    $this->actingAs($this->reviewer)->post(($this->url)('approve'))->assertSessionHasErrors('step');
    $this->actingAs($this->reviewer)->post(($this->url)('revision'), ['reason' => 'x'])->assertSessionHasErrors('step');

    expect($this->step->fresh()->status)->toBe(StepStatus::NotStarted);
});

it('lets exactly one decision through when a second reviewer arrives late', function () {
    $this->actingAs($this->leader)->post(($this->url)('submit'), stepPayload($this->step));
    $this->actingAs($this->reviewer)->post(($this->url)('review.start'));
    $this->actingAs($this->reviewer)->post(($this->url)('approve'))->assertSessionHasNoErrors();

    $other = stepReviewer();
    $this->actingAs($other)->post(($this->url)('approve'))->assertSessionHasErrors('step');
    $this->actingAs($other)->post(($this->url)('revision'), ['reason' => 'terlambat'])->assertSessionHasErrors('step');

    expect($this->step->histories()->whereIn('action', ['APPROVED', 'REVISION_REQUESTED'])->count())->toBe(1);
});

it('keeps approving apart from reviewing', function () {
    $this->actingAs($this->leader)->post(($this->url)('submit'), stepPayload($this->step));
    $reviewOnly = stepReviewer(canApprove: false);

    $this->actingAs($reviewOnly)->post(($this->url)('review.start'))->assertSessionHasNoErrors();
    $this->actingAs($reviewOnly)->post(($this->url)('approve'))->assertForbidden();
    $this->actingAs($reviewOnly)->post(($this->url)('revision'), ['reason' => 'perlu data'])->assertSessionHasNoErrors();
});

it('lets an assigned Mentor review and approve steps but denies another project', function () {
    $this->actingAs($this->leader)->post(($this->url)('submit'), stepPayload($this->step));
    $mentor = stepPerson('Mentor', ['projects.view', 'projects.review', 'projects.approve']);
    DB::table('project_mentors')->insert([
        'project_id' => $this->project->id,
        'mentor_id' => $mentor->id,
        'assigned_by' => $this->reviewer->id,
        'status' => 'ACTIVE',
        'assigned_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $secondStep = $this->project->steps()->where('step_number', 2)->firstOrFail();
    $this->actingAs($this->leader)->post(route('projects.steps.submit', [$this->project, 2]), stepPayload($secondStep));
    $this->actingAs($mentor)->post(route('projects.steps.review.start', [$this->project, 2]))->assertSessionHasNoErrors();
    $this->actingAs($mentor)->post(route('projects.steps.revision', [$this->project, 2]), ['reason' => 'Perlu bukti pendukung.'])->assertSessionHasNoErrors();
    expect($secondStep->fresh()->status)->toBe(StepStatus::RevisionRequired);

    $this->actingAs($mentor)->post(($this->url)('review.start'))->assertSessionHasNoErrors();
    $this->actingAs($mentor)->post(($this->url)('approve'))->assertSessionHasNoErrors();
    expect($this->step->fresh()->status)->toBe(StepStatus::Approved)
        ->and($mentor->can('approveProposal', $this->project))->toBeFalse();

    $otherProject = Project::factory()->status(ProposalStatus::Approved)->create();
    app(InitializeProjectSteps::class)->handle($otherProject);
    $otherStep = $otherProject->steps()->where('step_number', 1)->firstOrFail();
    $otherStep->forceFill(['status' => StepStatus::UnderReview])->save();

    $this->actingAs($mentor)->post(route('projects.steps.approve', [$otherProject, 1]))->assertForbidden();
    expect($otherStep->fresh()->status)->toBe(StepStatus::UnderReview);
});

it('keeps the team and the reviewers to their own jobs', function () {
    $this->actingAs($this->reviewer)->patch(($this->url)(), stepPayload($this->step))->assertForbidden();
    $this->actingAs($this->reviewer)->post(($this->url)('submit'), stepPayload($this->step))->assertForbidden();
    $this->actingAs($this->leader)->post(($this->url)('review.start'))->assertForbidden();
    $this->actingAs($this->leader)->post(($this->url)('approve'))->assertForbidden();

    $outsider = stepPerson('Team Leader', ['projects.view', 'projects.update', 'projects.submit']);
    $this->actingAs($outsider)->patch(($this->url)(), stepPayload($this->step))->assertForbidden();

    auth()->logout();
    $this->patch(($this->url)(), [])->assertRedirect(route('login'));

    expect($this->step->fresh()->status)->toBe(StepStatus::NotStarted);
});

it('refuses work on steps before the proposal is approved', function () {
    $this->project->forceFill(['proposal_status' => ProposalStatus::UnderReview])->save();

    $this->actingAs($this->leader)->patch(($this->url)(), stepPayload($this->step))->assertSessionHasErrors('step');
});

it('can require the steps to go in order when the policy asks for it', function () {
    config()->set('qcc.steps.require_sequence', true);
    $second = ProjectStep::query()->where('project_id', $this->project->id)->where('step_number', 2)->first();

    $this->actingAs($this->leader)->post(route('projects.steps.submit', [$this->project, 2]), stepPayload($second))->assertSessionHasErrors('step');

    $this->actingAs($this->leader)->post(($this->url)('submit'), stepPayload($this->step));
    $this->actingAs($this->reviewer)->post(($this->url)('review.start'));
    $this->actingAs($this->reviewer)->post(($this->url)('approve'));

    $this->actingAs($this->leader)->post(route('projects.steps.submit', [$this->project, 2]), stepPayload($second))->assertSessionHasNoErrors();
});

it('tells the page what the viewer may do and hands over the history', function () {
    $this->actingAs($this->leader)->post(($this->url)('submit'), stepPayload($this->step));

    $this->actingAs($this->leader)->get(route('projects.steps.show', [$this->project, 1]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('selected.status', 'SUBMITTED')
            ->where('selected.can_edit', false)->where('selected.can_start_review', false)
            ->where('selected.lock_version', 1)->has('selected.history', 1)
            ->where('selected.history.0.actor', $this->leader->name));

    $this->actingAs($this->reviewer)->get(route('projects.steps.show', [$this->project, 1]))
        ->assertInertia(fn (Assert $page) => $page->where('selected.can_start_review', true)->where('selected.can_approve', false));

    $this->actingAs($this->reviewer)->post(($this->url)('review.start'));

    $this->actingAs($this->reviewer)->get(route('projects.steps.show', [$this->project, 1]))
        ->assertInertia(fn (Assert $page) => $page->where('selected.can_approve', true)->where('selected.can_request_revision', true));

    $this->actingAs(stepTeamMember($this->project, 'SIMer / Member'))->get(route('projects.steps.show', [$this->project, 2]))
        ->assertInertia(fn (Assert $page) => $page->where('selected.can_edit', true)->where('selected.can_submit', true));
});
