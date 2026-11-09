<?php

use App\Actions\Proposals\InitializeProjectSteps;
use App\Actions\Proposals\ReviewProposal;
use App\Enums\ProposalStatus;
use App\Enums\QccStep;
use App\Enums\StepStatus;
use App\Models\Project;
use App\Models\ProjectStep;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function stepsViewer(string $role = 'Owner QCC', bool $viewAny = true): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate($role, 'web'));
    $user->givePermissionTo(Permission::findOrCreate('projects.view', 'web'));

    if ($viewAny) {
        $user->givePermissionTo(Permission::findOrCreate('projects.viewAny', 'web'));
    }

    return $user;
}

function approvedProject(): Project
{
    return Project::factory()->status(ProposalStatus::Approved)->create();
}

function setStep(Project $project, int $number, array $attributes): void
{
    app(InitializeProjectSteps::class)->handle($project);
    DB::table('qcc_steps')->where('project_id', $project->id)->where('step_number', $number)->update($attributes);
}

it('names the eight steps exactly as the BRD does', function () {
    expect(array_column(QccStep::catalog(), 'name'))->toBe([
        'Menentukan Tema Improvement',
        'Menetapkan Target',
        'Menemukan Sebab-Sebab yang Mungkin',
        'Menentukan Sebab-Sebab yang Dominan',
        'Membuat Rencana Perbaikan',
        'Implementasi Rencana Perbaikan',
        'Evaluasi Hasil Perbaikan',
        'Standarisasi & Rencana Pencegahan',
    ]);
});

it('creates exactly eight steps once and leaves existing ones alone when run again', function () {
    $project = approvedProject();
    $initialize = app(InitializeProjectSteps::class);

    $initialize->handle($project);
    DB::table('qcc_steps')->where('project_id', $project->id)->where('step_number', 3)->update(['progress_pct' => 40, 'status' => 'IN_PROGRESS']);
    $initialize->handle($project);

    $steps = ProjectStep::query()->where('project_id', $project->id)->orderBy('step_number')->get();

    expect($steps)->toHaveCount(8)
        ->and($steps->pluck('step_number')->all())->toBe([1, 2, 3, 4, 5, 6, 7, 8])
        ->and($steps->firstWhere('step_number', 3)->progress_pct)->toBe(40)
        ->and($steps->firstWhere('step_number', 1)->status)->toBe(StepStatus::NotStarted);
});

it('cannot hold the same step twice for one project', function () {
    $project = approvedProject();
    app(InitializeProjectSteps::class)->handle($project);

    expect(fn () => DB::table('qcc_steps')->insert([
        'project_id' => $project->id, 'step_number' => 2, 'created_at' => now(), 'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('shows all eight steps with the current one and the project progress', function () {
    $project = approvedProject();
    setStep($project, 1, ['status' => 'APPROVED', 'progress_pct' => 100]);
    setStep($project, 2, ['status' => 'IN_PROGRESS', 'progress_pct' => 50]);
    setStep($project, 3, ['status' => 'UNDER_REVIEW', 'progress_pct' => 80]);
    setStep($project, 4, ['status' => 'REVISION_REQUIRED', 'progress_pct' => 20]);

    $this->actingAs(stepsViewer())->get(route('projects.steps.show', $project))
        ->assertInertia(fn (Assert $page) => $page
            ->component('projects/steps')
            ->where('available', true)
            ->has('steps', 8)
            ->where('steps.0.name', 'Menentukan Tema Improvement')
            ->where('steps.7.name', 'Standarisasi & Rencana Pencegahan')
            ->where('steps.1.is_current', true)
            ->where('steps.0.is_current', false)
            ->where('selected.number', 2)
            ->where('summary.approved', 1)
            ->where('summary.in_review', 1)
            ->where('summary.needs_revision', 1)
            ->where('summary.overall_progress', (int) round((100 + 50 + 80 + 20) / 8))
            ->has('catalog', 8));
});

it('opens the step that was asked for and refuses numbers outside 1 to 8', function () {
    $project = approvedProject();
    setStep($project, 5, ['activity_description' => 'Susun rencana', 'plan_text' => 'Rencana A', 'target_date' => '2026-12-01']);

    $this->actingAs(stepsViewer())->get(route('projects.steps.show', [$project, 5]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('selected.number', 5)
            ->where('selected.name', 'Membuat Rencana Perbaikan')
            ->where('selected.activity_description', 'Susun rencana')
            ->where('selected.plan_text', 'Rencana A')
            ->where('selected.target_date', '2026-12-01'));

    $this->actingAs(stepsViewer())->get(route('projects.steps.show', [$project, 9]))->assertNotFound();
    $this->actingAs(stepsViewer())->get(route('projects.steps.show', [$project, 0]))->assertNotFound();
});

it('lands on the last step when every step is approved', function () {
    $project = approvedProject();
    app(InitializeProjectSteps::class)->handle($project);
    DB::table('qcc_steps')->where('project_id', $project->id)->update(['status' => 'APPROVED', 'progress_pct' => 100]);

    $this->actingAs(stepsViewer())->get(route('projects.steps.show', $project))
        ->assertInertia(fn (Assert $page) => $page->where('selected.number', 8)->where('summary.overall_progress', 100)->where('summary.approved', 8));
});

it('sets up the steps the first time an approved project is opened', function () {
    $project = approvedProject();

    expect(DB::table('qcc_steps')->where('project_id', $project->id)->count())->toBe(0);

    $this->actingAs(stepsViewer())->get(route('projects.steps.show', $project))->assertOk();

    expect(DB::table('qcc_steps')->where('project_id', $project->id)->count())->toBe(8);
});

it('holds the steps back until the proposal is approved', function () {
    $project = Project::factory()->status(ProposalStatus::Submitted)->create();

    $this->actingAs(stepsViewer())->get(route('projects.steps.show', $project))
        ->assertInertia(fn (Assert $page) => $page->where('available', false)->has('steps', 0)->where('selected', null)->has('catalog', 8));

    expect(DB::table('qcc_steps')->where('project_id', $project->id)->count())->toBe(0);
});

it('is visible only to people who can see the project', function () {
    $project = approvedProject();
    $stranger = stepsViewer('SIMer / Member', viewAny: false);

    $this->actingAs($stranger)->get(route('projects.steps.show', $project))->assertForbidden();

    DB::table('project_members')->insert([
        'project_id' => $project->id, 'user_id' => $stranger->id, 'role_in_project' => 'MEMBER',
        'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now(),
    ]);

    $this->actingAs($stranger)->get(route('projects.steps.show', $project))->assertOk();

    auth()->logout();
    $this->get(route('projects.steps.show', $project))->assertRedirect(route('login'));
});

it('opens the steps by itself when a reviewer approves the proposal', function () {
    $reviewer = stepsViewer();
    $project = Project::factory()->status(ProposalStatus::UnderReview)->create();
    $this->actingAs($reviewer);

    app(ReviewProposal::class)->decide($project, $reviewer, 'APPROVE', null);

    expect($project->fresh()->proposal_status)->toBe(ProposalStatus::Approved)
        ->and(DB::table('qcc_steps')->where('project_id', $project->id)->count())->toBe(8);
});
