<?php

use App\Actions\Proposals\InitializeProjectSteps;
use App\Enums\ProposalStatus;
use App\Jobs\InspectProjectEvidence;
use App\Models\BiweeklyReport;
use App\Models\Project;
use App\Models\ProjectEvidence;
use App\Models\ProjectStep;
use App\Models\QccBatch;
use App\Models\User;
use Database\Seeders\ProjectAccessSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function qccEvidenceUploader(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('Team Leader', 'web'));
    $user->givePermissionTo([
        Permission::findOrCreate('projects.view', 'web'),
        Permission::findOrCreate('evidence.view', 'web'),
        Permission::findOrCreate('evidence.upload', 'web'),
    ]);

    return $user;
}

function approvedEvidenceProject(User $leader): Project
{
    $project = Project::factory()->create([
        'batch_id' => QccBatch::factory()->create()->id,
        'proposer_id' => $leader->id,
        'proposal_status' => ProposalStatus::Approved,
    ]);

    $project->members()->attach($leader->id, [
        'role_in_project' => 'LEADER',
        'status' => 'ACTIVE',
        'joined_at' => now(),
    ]);

    return $project;
}

function validEvidencePdf(int $paddingBytes = 0): UploadedFile
{
    return UploadedFile::fake()->createWithContent(
        'hasil.pdf',
        "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n".str_repeat(' ', $paddingBytes)."\n%%EOF",
    );
}

function evidenceUploadPayload(
    Project $project,
    ?UploadedFile $file = null,
    string $targetType = 'PROJECT',
    ?int $targetId = null,
): array {
    return [
        'title' => 'Hasil pengukuran',
        'category' => 'QCC_STEP',
        'description' => 'Data pendukung',
        'target_type' => $targetType,
        'target_id' => $targetId ?? $project->id,
        'file' => $file ?? validEvidencePdf(),
    ];
}

test('evidence versions remain private and pending files cannot be previewed or downloaded', function () {
    Storage::fake('evidence_private');
    Queue::fake();
    $leader = qccEvidenceUploader();
    $project = approvedEvidenceProject($leader);

    $this->actingAs($leader)
        ->post(route('projects.evidence.store', $project), evidenceUploadPayload($project, validEvidencePdf(11 * 1024 * 1024)))
        ->assertRedirect();
    $first = ProjectEvidence::query()->firstOrFail();
    Storage::disk('evidence_private')->assertExists($first->storage_key);
    expect($first->version_number)->toBe(1)
        ->and($first->links()->count())->toBe(1)
        ->and($first->links()->first()->target_id)->toBe($project->id);

    $this->post(route('projects.evidence.store', $project), evidenceUploadPayload($project))
        ->assertRedirect();
    $second = ProjectEvidence::query()->latest('id')->firstOrFail();

    expect($second->version_number)->toBe(2)
        ->and($second->version_group)->toBe($first->version_group)
        ->and($second->supersedes_id)->toBe($first->id)
        ->and($first->fresh()->version_number)->toBe(1);

    $this->get(route('projects.evidence.preview', [$project, $first]))->assertNotFound();
    $this->get(route('projects.evidence.download', [$project, $first]))->assertNotFound();

    $first->forceFill(['scan_status' => 'PASSED'])->save();
    $download = $this->get(route('projects.evidence.download', [$project, $first]))
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertHeader('Content-Disposition', 'attachment; filename=hasil.pdf');

    $this->assertStringContainsString('%PDF-1.4', $download->streamedContent());
    expect(Queue::pushed(InspectProjectEvidence::class)->count())->toBe(2);
});

test('seeded SIMer members can view and upload evidence for their active team project only', function () {
    Storage::fake('evidence_private');
    Queue::fake();
    $this->seed(ProjectAccessSeeder::class);

    $member = User::factory()->create();
    $member->assignRole(Role::findOrCreate('SIMer / Member', 'web'));
    $project = Project::factory()->create([
        'batch_id' => QccBatch::factory()->create()->id,
        'proposer_id' => $member->id,
        'proposal_status' => ProposalStatus::Approved,
    ]);
    $project->members()->attach($member->id, [
        'role_in_project' => 'MEMBER',
        'status' => 'ACTIVE',
        'joined_at' => now(),
    ]);

    $this->actingAs($member)
        ->get(route('projects.evidence.index', $project))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('can_upload', true));

    $this->post(route('projects.evidence.store', $project), evidenceUploadPayload($project))
        ->assertRedirect();

    expect(ProjectEvidence::query()->where('project_id', $project->id)->exists())->toBeTrue();

    $outsider = User::factory()->create();
    $outsider->assignRole(Role::findOrCreate('SIMer / Member', 'web'));

    $this->actingAs($outsider)
        ->get(route('projects.evidence.index', $project))
        ->assertForbidden();
});

test('evidence upload rejects file content that does not match its extension', function () {
    Storage::fake('evidence_private');
    Queue::fake();
    $leader = qccEvidenceUploader();
    $project = approvedEvidenceProject($leader);
    $forgedPdf = UploadedFile::fake()->createWithContent('palsu.pdf', 'bukan file PDF');

    $this->actingAs($leader)
        ->post(route('projects.evidence.store', $project), evidenceUploadPayload($project, $forgedPdf))
        ->assertSessionHasErrors('file');

    expect(ProjectEvidence::query()->count())->toBe(0)
        ->and(Queue::pushed(InspectProjectEvidence::class))->toBeEmpty();
});

test('evidence can be linked to a QCC step in the same project', function () {
    Storage::fake('evidence_private');
    Queue::fake();
    $leader = qccEvidenceUploader();
    $project = approvedEvidenceProject($leader);
    app(InitializeProjectSteps::class)->handle($project);
    $step = $project->steps()->firstOrFail();

    $this->actingAs($leader)
        ->post(
            route('projects.evidence.store', $project),
            evidenceUploadPayload($project, targetType: 'QCC_STEP', targetId: $step->id),
        )
        ->assertRedirect();

    $link = ProjectEvidence::query()->firstOrFail()->links()->firstOrFail();
    expect($link->target_type)->toBe(ProjectStep::class)
        ->and($link->target_id)->toBe($step->id);
});

test('evidence can be linked only to a report from the same project', function () {
    Storage::fake('evidence_private');
    Queue::fake();
    $leader = qccEvidenceUploader();
    $project = approvedEvidenceProject($leader);
    $report = BiweeklyReport::query()->create([
        'project_id' => $project->id,
        'period_no' => 1,
        'starts_on' => now()->toDateString(),
        'ends_on' => now()->addDays(13)->toDateString(),
        'created_by' => $leader->id,
    ]);

    $this->actingAs($leader)
        ->post(
            route('projects.evidence.store', $project),
            evidenceUploadPayload($project, targetType: 'BIWEEKLY_REPORT', targetId: $report->id),
        )
        ->assertRedirect();

    $link = ProjectEvidence::query()->firstOrFail()->links()->firstOrFail();
    expect($link->target_type)->toBe(BiweeklyReport::class)
        ->and($link->target_id)->toBe($report->id);

    $otherProject = approvedEvidenceProject($leader);
    $otherReport = BiweeklyReport::query()->create([
        'project_id' => $otherProject->id,
        'period_no' => 1,
        'starts_on' => now()->toDateString(),
        'ends_on' => now()->addDays(13)->toDateString(),
        'created_by' => $leader->id,
    ]);

    $this->post(
        route('projects.evidence.store', $project),
        evidenceUploadPayload($project, targetType: 'BIWEEKLY_REPORT', targetId: $otherReport->id),
    )->assertNotFound();

    expect(ProjectEvidence::query()->count())->toBe(1);
});

test('image evidence is inspected asynchronously and produces a private thumbnail', function () {
    Storage::fake('evidence_private');
    Queue::fake();
    $leader = qccEvidenceUploader();
    $project = approvedEvidenceProject($leader);
    $file = UploadedFile::fake()->image('diagram.png', 120, 80);

    $this->actingAs($leader)
        ->post(route('projects.evidence.store', $project), evidenceUploadPayload($project, $file))
        ->assertRedirect();

    $evidence = ProjectEvidence::query()->firstOrFail();
    expect($evidence->scan_status)->toBe('PENDING')
        ->and($evidence->processing_status)->toBe('PENDING');
    Queue::assertPushed(InspectProjectEvidence::class);

    app()->call([new InspectProjectEvidence($evidence->id), 'handle']);
    $evidence->refresh();

    expect($evidence->scan_status)->toBe('PASSED')
        ->and($evidence->processing_status)->toBe('READY')
        ->and($evidence->thumbnail_path)->not->toBeNull();
    Storage::disk('evidence_private')->assertExists($evidence->thumbnail_path);

    $this->get(route('projects.evidence.thumbnail', [$project, $evidence]))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('Cache-Control', 'no-store, private');

    expect((new InspectProjectEvidence($evidence->id))->tries)->toBe(3)
        ->and((new InspectProjectEvidence($evidence->id))->backoff)->toBe([30, 120]);
});

test('pdf inspection records extraction availability without blocking the upload request', function () {
    Storage::fake('evidence_private');
    Queue::fake();
    $leader = qccEvidenceUploader();
    $project = approvedEvidenceProject($leader);

    $this->actingAs($leader)
        ->post(route('projects.evidence.store', $project), evidenceUploadPayload($project))
        ->assertRedirect();

    $evidence = ProjectEvidence::query()->firstOrFail();
    expect($evidence->scan_status)->toBe('PENDING');
    Queue::assertPushed(InspectProjectEvidence::class);

    config()->set('qcc_evidence.pdf_text_extractor', 'qcc-missing-pdftotext');
    app()->call([new InspectProjectEvidence($evidence->id), 'handle']);
    $evidence->refresh();

    expect($evidence->scan_status)->toBe('PASSED')
        ->and($evidence->processing_status)->toBe('READY')
        ->and($evidence->extraction_status)->toBe('UNAVAILABLE');
});

test('users cannot access evidence from another project or a private project they do not belong to', function () {
    Storage::fake('evidence_private');
    $leader = qccEvidenceUploader();
    $project = approvedEvidenceProject($leader);
    $otherProject = approvedEvidenceProject($leader);
    $evidence = ProjectEvidence::query()->create([
        'project_id' => $project->id,
        'uploaded_by' => $leader->id,
        'title' => 'Dokumen privat',
        'category' => 'OTHER',
        'source_type' => 'FILE',
        'storage_disk' => 'evidence_private',
        'storage_key' => 'projects/'.$project->id.'/private.pdf',
        'original_name' => 'private.pdf',
        'file_extension' => 'pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 4,
        'checksum_sha256' => hash('sha256', 'data'),
        'version_group' => (string) str()->uuid(),
        'version_number' => 1,
        'scan_status' => 'PASSED',
    ]);
    Storage::disk('evidence_private')->put($evidence->storage_key, 'data');

    $this->actingAs($leader)
        ->get(route('projects.evidence.download', [$otherProject, $evidence]))
        ->assertNotFound();

    $outsider = qccEvidenceUploader();
    $this->actingAs($outsider)
        ->get(route('projects.evidence.download', [$project, $evidence]))
        ->assertForbidden();
});
