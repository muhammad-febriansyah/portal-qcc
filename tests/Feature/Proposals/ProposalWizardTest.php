<?php

use App\Enums\ProposalStatus;
use App\Enums\QccStep;
use App\Models\Competency;
use App\Models\Project;
use App\Models\ProjectAttachment;
use App\Models\QccBatch;
use App\Models\User;
use App\Notifications\ProposalWorkflowNotification;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
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
        'current_situation' => '',
        'improvement_plan' => '',
        'target' => '',
        'scope' => '',
        'supplementary_information' => '',
        'cost_projection' => '',
        'learning_goals' => ['', '', ''],
        'step_timeline' => array_fill(0, count(QccStep::cases()), ''),
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

it('saves draft fields without moving the wizard forward', function () {
    $user = proposalWizardCreator();
    $project = Project::factory()->create([
        'proposer_id' => $user->id,
        'wizard_step' => 1,
        'title' => 'Judul awal',
    ]);

    $this->actingAs($user)
        ->patch(route('proposals.update', $project), proposalDraftPayload($project->batch, [
            'title' => 'Judul yang diperbarui',
            'wizard_step' => 5,
            'advance' => false,
        ]))
        ->assertRedirect(route('proposals.edit', $project));

    expect($project->fresh())
        ->title->toBe('Judul yang diperbarui')
        ->wizard_step->toBe(1);
});

it('rejects advancing past the next available wizard step', function () {
    $user = proposalWizardCreator();
    $project = Project::factory()->create([
        'proposer_id' => $user->id,
        'wizard_step' => 1,
    ]);

    $this->actingAs($user)
        ->patch(route('proposals.update', $project), proposalDraftPayload($project->batch, [
            'wizard_step' => 3,
            'advance' => true,
            'target' => 'Turunkan waktu proses',
            'scope' => 'Proses verifikasi dokumen',
            'improvement_plan' => 'Satukan pemeriksaan',
        ]))
        ->assertSessionHasErrors(['wizard_step']);

    expect($project->fresh()->wizard_step)->toBe(1);
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

it('requires background and the merged situation and baseline before advancing', function () {
    $user = proposalWizardCreator();
    $project = Project::factory()->create(['proposer_id' => $user->id, 'wizard_step' => 2]);

    $this->actingAs($user)
        ->patch(route('proposals.update', $project), proposalDraftPayload($project->batch, [
            'wizard_step' => 2,
            'advance' => true,
        ]))
        ->assertSessionHasErrors(['background', 'current_situation'])
        ->assertSessionDoesntHaveErrors(['problem_statement', 'baseline']);

    expect($project->fresh()->wizard_step)->toBe(2);
});

it('requires the improvement plan, target and scope before advancing', function () {
    $user = proposalWizardCreator();
    $project = Project::factory()->create(['proposer_id' => $user->id, 'wizard_step' => 3]);

    $this->actingAs($user)
        ->patch(route('proposals.update', $project), proposalDraftPayload($project->batch, [
            'wizard_step' => 3,
            'advance' => true,
        ]))
        ->assertSessionHasErrors(['improvement_plan', 'target', 'scope'])
        ->assertSessionDoesntHaveErrors(['rca', 'business_benefit']);

    expect($project->fresh()->wizard_step)->toBe(3);
});

it('requires the project cost projection before advancing', function () {
    $user = proposalWizardCreator();
    $project = Project::factory()->create(['proposer_id' => $user->id, 'wizard_step' => 4]);

    $this->actingAs($user)
        ->patch(route('proposals.update', $project), proposalDraftPayload($project->batch, [
            'wizard_step' => 4,
            'advance' => true,
        ]))
        ->assertSessionHasErrors(['cost_projection'])
        ->assertSessionDoesntHaveErrors(['supplementary_information']);

    expect($project->fresh()->wizard_step)->toBe(4);
});

it('requires three learning goals and a target date for every QCC step before advancing', function () {
    $user = proposalWizardCreator();
    $project = Project::factory()->create(['proposer_id' => $user->id, 'wizard_step' => 5]);

    $this->actingAs($user)
        ->patch(route('proposals.update', $project), proposalDraftPayload($project->batch, [
            'wizard_step' => 5,
            'advance' => true,
        ]))
        ->assertSessionHasErrors(['learning_goals.0', 'learning_goals.1', 'learning_goals.2', 'step_timeline.0', 'step_timeline.7']);

    expect($project->fresh()->wizard_step)->toBe(5);
});

it('stores the step timeline and refuses a step due before the previous one', function () {
    $user = proposalWizardCreator();
    $project = Project::factory()->create(['proposer_id' => $user->id, 'wizard_step' => 5]);
    $dates = array_map(fn (QccStep $step): string => now()->addWeeks($step->value)->toDateString(), QccStep::cases());
    $payload = proposalDraftPayload($project->batch, [
        'wizard_step' => 5,
        'advance' => true,
        'learning_goals' => ['Satu', 'Dua', 'Tiga'],
    ]);

    $outOfOrder = $dates;
    $outOfOrder[3] = now()->toDateString();
    $this->actingAs($user)
        ->patch(route('proposals.update', $project), [...$payload, 'step_timeline' => $outOfOrder])
        ->assertSessionHasErrors(['step_timeline.3']);

    $this->patch(route('proposals.update', $project), [...$payload, 'step_timeline' => $dates])
        ->assertSessionHasNoErrors();

    expect($project->fresh())
        ->wizard_step->toBe(6)
        ->step_timeline_json->toBe(array_map(fn (QccStep $step): array => [
            'step_number' => $step->value,
            'target_date' => $dates[$step->value - 1],
        ], QccStep::cases()));
});

it('requires at least one competency need before advancing to the preview', function () {
    $user = proposalWizardCreator();
    $project = Project::factory()->create(['proposer_id' => $user->id, 'wizard_step' => 6]);
    $payload = proposalDraftPayload($project->batch, ['wizard_step' => 6, 'advance' => true]);

    $this->actingAs($user)
        ->patch(route('proposals.update', $project), $payload)
        ->assertSessionHasErrors(['requirements']);

    DB::table('project_requirements')->insert([
        'project_id' => $project->id,
        'competency_id' => Competency::factory()->create()->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->patch(route('proposals.update', $project), $payload)->assertSessionHasNoErrors();
    expect($project->fresh()->wizard_step)->toBe(7);
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
            'files' => [UploadedFile::fake()->createWithContent('supporting-document.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF")],
        ])
        ->assertRedirect(route('proposals.edit', $project));

    $attachment = ProjectAttachment::query()->firstOrFail();
    Storage::disk('local')->assertExists($attachment->path);
    expect($attachment->scan_status)->toBe('PASSED');

    $this->get(route('proposals.attachments.preview', [$project, $attachment]))
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    $otherUser->givePermissionTo(Permission::findOrCreate('projects.view', 'web'));

    $this->actingAs($otherUser)
        ->get(route('proposals.attachments.preview', [$project, $attachment]))
        ->assertForbidden();
});

it('rejects invalid proposal attachment content before storing a file', function () {
    Storage::fake('local');
    $owner = proposalWizardCreator();
    $project = Project::factory()->create([
        'proposer_id' => $owner->id,
        'proposal_status' => ProposalStatus::Draft,
    ]);

    $this->actingAs($owner)->post(route('proposals.attachments.store', $project), [
        'files' => [UploadedFile::fake()->createWithContent('forged.pdf', 'not a PDF')],
    ])->assertSessionHasErrors('files');

    expect(ProjectAttachment::query()->count())->toBe(0);
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

it('inspects old pending proposal attachments without deleting their files', function () {
    Storage::fake('local');
    $owner = proposalWizardCreator();
    $project = Project::factory()->create([
        'proposer_id' => $owner->id,
        'proposal_status' => ProposalStatus::Draft,
    ]);
    $validPath = 'qcc/proposals/'.$project->id.'/old.pdf';
    $invalidPath = 'qcc/proposals/'.$project->id.'/invalid.pdf';
    Storage::disk('local')->put($validPath, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF");
    Storage::disk('local')->put($invalidPath, 'not a PDF');

    $valid = ProjectAttachment::query()->create([
        'project_id' => $project->id, 'uploaded_by' => $owner->id, 'disk' => 'local',
        'path' => $validPath, 'original_name' => 'old.pdf', 'mime_type' => 'application/pdf',
        'size_bytes' => 70, 'scan_status' => 'PENDING_SCAN',
    ]);
    $invalid = ProjectAttachment::query()->create([
        'project_id' => $project->id, 'uploaded_by' => $owner->id, 'disk' => 'local',
        'path' => $invalidPath, 'original_name' => 'invalid.pdf', 'mime_type' => 'application/pdf',
        'size_bytes' => 9, 'scan_status' => 'PENDING_SCAN',
    ]);

    $this->artisan('qcc:proposals:inspect-attachments')->assertSuccessful();
    expect($valid->fresh()->scan_status)->toBe('PASSED')
        ->and($invalid->fresh()->scan_status)->toBe('REJECTED');
    Storage::disk('local')->assertExists([$validPath, $invalidPath]);

    $this->actingAs($owner)->get(route('proposals.attachments.preview', [$project, $valid]))->assertOk();
    $this->get(route('proposals.attachments.preview', [$project, $invalid]))->assertNotFound();
});

it('lets SIMer submit without a team and tells them Owner QCC will form it', function () {
    $user = proposalWizardCreator();
    $user->givePermissionTo(Permission::findOrCreate('projects.submit', 'web'));
    $project = Project::factory()->create([
        'proposer_id' => $user->id,
        'proposal_status' => ProposalStatus::Draft,
        'title' => 'Perbaikan waktu proses',
        'theme' => 'Efisiensi',
        'background' => 'Latar belakang',
        'current_situation' => 'Situasi saat ini dan baseline',
        'improvement_plan' => 'Rencana',
        'target' => 'Target',
        'scope' => 'Ruang lingkup',
        'cost_projection' => 'Tidak ada biaya',
        'learning_goals_json' => ['Tujuan satu', 'Tujuan dua', 'Tujuan tiga'],
        'step_timeline_json' => array_map(fn (QccStep $step): array => [
            'step_number' => $step->value,
            'target_date' => now()->addWeeks($step->value)->toDateString(),
        ], QccStep::cases()),
    ]);
    DB::table('project_requirements')->insert([
        'project_id' => $project->id,
        'competency_id' => Competency::factory()->create()->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->actingAs($user)
        ->get(route('proposals.show', $project))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('proposal.team_confirmed', false)
            ->where('proposal.can_submit', true)
            ->where('proposal.cost_projection', 'Tidak ada biaya')
            ->has('proposal.step_timeline', 8)
            ->where('proposal.team_status_message', 'Tim akan disusun oleh Owner QCC setelah proposal diajukan.'));

    $this->post(route('proposals.submit', $project))->assertSessionHasNoErrors();
    expect($project->fresh()->proposal_status)->toBe(ProposalStatus::Submitted);
});

it('no longer asks Owner QCC to compose a team while the proposal is a draft', function () {
    $user = proposalWizardCreator();
    $batch = QccBatch::factory()->active()->create();
    $project = Project::factory()->create([
        'batch_id' => $batch->id,
        'proposer_id' => $user->id,
        'wizard_step' => 7,
    ]);
    $owner = User::factory()->create();
    $owner->assignRole(Role::findOrCreate('Owner QCC', 'web'));
    $owner->givePermissionTo([
        Permission::findOrCreate('projects.view', 'web'),
        Permission::findOrCreate('projects.viewAny', 'web'),
        Permission::findOrCreate('projects.review', 'web'),
    ]);

    $this->actingAs($user)->patch(route('proposals.update', $project), proposalDraftPayload($batch, [
        'wizard_step' => 7,
        'background' => 'Latar belakang lengkap',
        'current_situation' => 'Kondisi saat ini',
        'improvement_plan' => 'Rencana perbaikan',
        'target' => 'Target proses',
        'scope' => 'Ruang lingkup proses',
        'cost_projection' => 'Tidak ada biaya',
        'learning_goals' => ['Tujuan satu', 'Tujuan dua', 'Tujuan tiga'],
    ]))->assertRedirect();

    expect($owner->notifications()->where('type', ProposalWorkflowNotification::class)->count())->toBe(0);
});

it('rejects a draft edit when proposal status changes after route binding', function () {
    $owner = proposalWizardCreator();
    $batch = QccBatch::factory()->active()->create();
    $project = Project::factory()->create([
        'batch_id' => $batch->id,
        'proposer_id' => $owner->id,
        'proposal_status' => ProposalStatus::Draft,
        'title' => 'Judul asli',
    ]);
    $url = route('proposals.update', $project);
    $switched = false;

    Event::listen('eloquent.retrieved: '.Project::class, function (Project $bound) use ($project, &$switched): void {
        if (! $switched && $bound->id === $project->id) {
            $switched = true;
            DB::table('projects')->where('id', $project->id)->update(['proposal_status' => ProposalStatus::Submitted->value]);
        }
    });

    $this->actingAs($owner)
        ->patch($url, proposalDraftPayload($batch, ['title' => 'Perubahan terlambat']))
        ->assertNotFound();

    expect($project->fresh()->title)->toBe('Judul asli')
        ->and($project->fresh()->proposal_status)->toBe(ProposalStatus::Submitted);
});

it('rejects an attachment upload when proposal status changes after route binding', function () {
    Storage::fake('local');
    $owner = proposalWizardCreator();
    $project = Project::factory()->create([
        'proposer_id' => $owner->id,
        'proposal_status' => ProposalStatus::Draft,
    ]);
    $url = route('proposals.attachments.store', $project);
    $switched = false;

    Event::listen('eloquent.retrieved: '.Project::class, function (Project $bound) use ($project, &$switched): void {
        if (! $switched && $bound->id === $project->id) {
            $switched = true;
            DB::table('projects')->where('id', $project->id)->update(['proposal_status' => ProposalStatus::Submitted->value]);
        }
    });

    $this->actingAs($owner)->post($url, [
        'files' => [UploadedFile::fake()->createWithContent('valid.pdf', "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF")],
    ])->assertNotFound();

    expect(ProjectAttachment::query()->count())->toBe(0)
        ->and(Storage::disk('local')->allFiles())->toBe([]);
});
