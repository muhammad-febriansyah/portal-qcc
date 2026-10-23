<?php

use App\Enums\ProposalStatus;
use App\Models\Project;
use App\Models\ProjectAttachment;
use App\Models\QccBatch;
use App\Models\TeamMatchingRun;
use App\Models\User;
use App\Notifications\ProposalWorkflowNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function proposalWizardCreator(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('SIMer / Member', 'web'));
    $user->givePermissionTo([
        Permission::findOrCreate('projects.view', 'web'),
        Permission::findOrCreate('projects.create', 'web'),
        Permission::findOrCreate('projects.update', 'web'),
    ]);

    return $user;
}

/** @return array<string, mixed> */
function proposalDraftPayload(QccBatch $batch, array $overrides = []): array
{
    return [
        'batch_id' => $batch->id,
        'title' => 'Mengurangi waktu tunggu proses',
        'theme' => 'Efisiensi proses',
        'background' => '',
        'problem_statement' => '',
        'current_situation' => '',
        'baseline' => '',
        'target' => '',
        'scope' => '',
        'learning_goals' => ['', '', ''],
        'rca' => '',
        'improvement_plan' => '',
        'business_benefit' => '',
        'wizard_step' => 1,
        'advance' => false,
        ...$overrides,
    ];
}

it('creates and resumes a proposal draft with its saved wizard step', function () {
    $user = proposalWizardCreator();
    $batch = QccBatch::factory()->active()->create();

    $this->actingAs($user)
        ->get(route('proposals.create'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('proposals/wizard')
            ->where('project', null)
            ->has('batches', 1));

    $response = $this->post(route('proposals.store'), proposalDraftPayload($batch, [
        'proposer_id' => 999999,
        'proposal_status' => ProposalStatus::Approved->value,
    ]));
    $project = Project::query()->firstOrFail();

    $response->assertRedirect(route('proposals.edit', $project));
    $this->assertModelExists($project);
    expect($project)
        ->proposal_status->toBe(ProposalStatus::Draft)
        ->proposer_id->toBe($user->id)
        ->title->toBe('Mengurangi waktu tunggu proses')
        ->wizard_step->toBe(1);

    $this->get(route('proposals.edit', $project))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('proposals/wizard')
            ->where('project.id', $project->id)
            ->where('project.title', 'Mengurangi waktu tunggu proses')
            ->where('step', 1));

    $this->get(route('proposals.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('drafts', 1)
            ->where('drafts.0.id', $project->id)
            ->where('can_create', true));

    $this->patch(route('proposals.update', $project), proposalDraftPayload($batch, [
        'wizard_step' => 1,
        'advance' => true,
    ]))->assertRedirect(route('proposals.edit', $project));

    expect($project->fresh()->wizard_step)->toBe(2);
});

it('requires every field in the current stage before advancing', function () {
    $user = proposalWizardCreator();
    $batch = QccBatch::factory()->active()->create();

    $this->actingAs($user)
        ->post(route('proposals.store'), proposalDraftPayload($batch, ['theme' => '', 'advance' => true]))
        ->assertSessionHasErrors(['theme']);

    expect(Project::query()->count())->toBe(0);
});

it('rejects drafts for inactive batches', function () {
    $user = proposalWizardCreator();
    $batch = QccBatch::factory()->create();

    $this->actingAs($user)
        ->post(route('proposals.store'), proposalDraftPayload($batch))
        ->assertSessionHasErrors(['batch_id']);

    expect(Project::query()->count())->toBe(0);
});

it('requires all problem and baseline fields before advancing to targets', function () {
    $user = proposalWizardCreator();
    $project = Project::factory()->create(['proposer_id' => $user->id, 'wizard_step' => 2]);

    $this->actingAs($user)
        ->patch(route('proposals.update', $project), proposalDraftPayload($project->batch, [
            'wizard_step' => 2,
            'advance' => true,
        ]))
        ->assertSessionHasErrors(['background', 'problem_statement', 'current_situation', 'baseline']);

    expect($project->fresh()->wizard_step)->toBe(2);
});

it('requires all target and benefit fields before advancing to learning goals', function () {
    $user = proposalWizardCreator();
    $project = Project::factory()->create(['proposer_id' => $user->id, 'wizard_step' => 3]);

    $this->actingAs($user)
        ->patch(route('proposals.update', $project), proposalDraftPayload($project->batch, [
            'wizard_step' => 3,
            'advance' => true,
        ]))
        ->assertSessionHasErrors(['target', 'scope', 'rca', 'improvement_plan', 'business_benefit']);

    expect($project->fresh()->wizard_step)->toBe(3);
});

it('requires three learning goals before advancing to attachments', function () {
    $user = proposalWizardCreator();
    $project = Project::factory()->create(['proposer_id' => $user->id, 'wizard_step' => 4]);

    $this->actingAs($user)
        ->patch(route('proposals.update', $project), proposalDraftPayload($project->batch, [
            'wizard_step' => 4,
            'advance' => true,
            'learning_goals' => ['', '', ''],
        ]))
        ->assertSessionHasErrors(['learning_goals.0', 'learning_goals.1', 'learning_goals.2']);

    expect($project->fresh()->wizard_step)->toBe(4);
});

it('prevents editing another users proposal draft', function () {
    $owner = proposalWizardCreator();
    $otherUser = proposalWizardCreator();
    $project = Project::factory()->create(['proposer_id' => $owner->id]);

    $this->actingAs($otherUser)
        ->get(route('proposals.edit', $project))
        ->assertForbidden();

    $this->patch(route('proposals.update', $project), proposalDraftPayload($project->batch))
        ->assertForbidden();
});

it('forbids proposal creation for roles without the create permission', function (string $roleName) {
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate($roleName, 'web'));

    $this->actingAs($user)
        ->get(route('proposals.create'))
        ->assertForbidden();
})->with(['Mentor', 'Owner QCC', 'Super Admin']);

it('stores proposal attachments privately and prevents other users from previewing them', function () {
    Storage::fake('local');
    $owner = proposalWizardCreator();
    $otherUser = User::factory()->create();
    $batch = QccBatch::factory()->active()->create();
    $project = Project::factory()->create([
        'batch_id' => $batch->id,
        'proposer_id' => $owner->id,
        'proposal_status' => ProposalStatus::Draft,
    ]);

    $this->actingAs($owner)
        ->post(route('proposals.attachments.store', $project), [
            'files' => [UploadedFile::fake()->create('supporting-document.pdf', 24, 'application/pdf')],
        ])
        ->assertRedirect(route('proposals.edit', $project));

    $attachment = ProjectAttachment::query()->firstOrFail();
    Storage::disk('local')->assertExists($attachment->path);

    $this->get(route('proposals.attachments.preview', [$project, $attachment]))
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    $otherUser->givePermissionTo(Permission::findOrCreate('projects.view', 'web'));

    $this->actingAs($otherUser)
        ->get(route('proposals.attachments.preview', [$project, $attachment]))
        ->assertForbidden();
});

it('shows SIMer that Owner QCC must confirm team and blocks submission first', function () {
    $user = proposalWizardCreator();
    $user->givePermissionTo(Permission::findOrCreate('projects.submit', 'web'));
    $project = Project::factory()->create([
        'proposer_id' => $user->id,
        'proposal_status' => ProposalStatus::Draft,
        'title' => 'Perbaikan waktu proses',
        'theme' => 'Efisiensi',
        'background' => 'Latar belakang',
        'problem_statement' => 'Masalah',
        'current_situation' => 'Situasi saat ini',
        'baseline' => 'Baseline',
        'target' => 'Target',
        'scope' => 'Ruang lingkup',
        'learning_goals_json' => ['Tujuan satu', 'Tujuan dua', 'Tujuan tiga'],
        'rca' => 'Akar masalah',
        'improvement_plan' => 'Rencana',
        'business_benefit' => 'Manfaat',
    ]);
    $project->members()->attach($user->id, [
        'role_in_project' => 'MEMBER',
        'status' => 'ACTIVE',
        'joined_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('proposals.show', $project))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('proposal.team_confirmed', false)
            ->where('proposal.can_submit', true)
            ->where('proposal.team_status_message', 'Tim akan disusun oleh Owner QCC. Proposal bisa diajukan setelah tim dikonfirmasi.'));

    TeamMatchingRun::query()->create([
        'project_id' => $project->id,
        'requested_by' => $user->id,
        'status' => 'READY',
        'idempotency_key' => (string) str()->uuid(),
    ]);

    $this->post(route('proposals.submit', $project))->assertSessionHasErrors('team');
    expect($project->fresh()->proposal_status)->toBe(ProposalStatus::Draft);
});

it('notifies active Owner QCC once when a complete SIMer draft still needs team composition', function () {
    $user = proposalWizardCreator();
    $batch = QccBatch::factory()->active()->create();
    $project = Project::factory()->create([
        'batch_id' => $batch->id,
        'proposer_id' => $user->id,
        'wizard_step' => 5,
        'title' => 'Mengurangi waktu tunggu',
        'theme' => 'Efisiensi proses',
        'background' => 'Latar belakang lengkap',
        'problem_statement' => 'Masalah lengkap',
        'current_situation' => 'Kondisi saat ini',
        'baseline' => 'Baseline proses',
        'target' => 'Target proses',
        'scope' => 'Ruang lingkup proses',
        'learning_goals_json' => ['Tujuan satu', 'Tujuan dua', 'Tujuan tiga'],
        'rca' => 'Analisis akar masalah',
        'improvement_plan' => 'Rencana perbaikan',
        'business_benefit' => 'Manfaat bisnis',
    ]);
    $owner = User::factory()->create();
    $owner->assignRole(Role::findOrCreate('Owner QCC', 'web'));
    $owner->givePermissionTo([
        Permission::findOrCreate('projects.view', 'web'),
        Permission::findOrCreate('projects.viewAny', 'web'),
        Permission::findOrCreate('projects.review', 'web'),
    ]);

    $payload = proposalDraftPayload($batch, [
        'wizard_step' => 5,
        'advance' => false,
        'background' => 'Latar belakang lengkap',
        'problem_statement' => 'Masalah lengkap',
        'current_situation' => 'Kondisi saat ini',
        'baseline' => 'Baseline proses',
        'target' => 'Target proses',
        'scope' => 'Ruang lingkup proses',
        'learning_goals' => ['Tujuan satu', 'Tujuan dua', 'Tujuan tiga'],
        'rca' => 'Analisis akar masalah',
        'improvement_plan' => 'Rencana perbaikan',
        'business_benefit' => 'Manfaat bisnis',
    ]);
    $this->actingAs($user)->patch(route('proposals.update', $project), $payload)->assertRedirect();
    $this->patch(route('proposals.update', $project), $payload)->assertRedirect();

    $notifications = $owner->notifications()->where('type', ProposalWorkflowNotification::class)->get();
    expect($notifications)->toHaveCount(1)
        ->and($notifications->sole()->data)->toMatchArray([
            'project_id' => $project->id,
            'title' => 'Proposal menunggu penyusunan tim',
            'decision' => 'TEAM_COMPOSITION_PENDING',
            'url' => route('proposals.candidates', $project, false),
        ]);
});
