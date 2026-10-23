<?php

use App\Enums\ProposalStatus;
use App\Jobs\InspectProjectEvidence;
use App\Models\AuditLog;
use App\Models\Project;
use App\Models\ProjectEvidence;
use App\Models\QccBatch;
use App\Models\User;
use App\Services\Scoring\Contracts\EvidenceResolver;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function evidenceSourceUploader(): User
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

function evidenceSourceProject(User $leader): Project
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

function evidenceSourcePayload(Project $project, array $overrides = []): array
{
    return array_replace([
        'title' => 'Referensi perbaikan proses',
        'category' => 'OTHER',
        'description' => 'Data pendukung tim',
        'target_type' => 'PROJECT',
        'target_id' => $project->id,
        'source_type' => 'FILE',
        'file' => UploadedFile::fake()->createWithContent(
            'hasil.pdf',
            "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF",
        ),
    ], $overrides);
}

test('file evidence remains scanned while link types are stored without file processing', function () {
    Storage::fake('evidence_private');
    Queue::fake();
    $leader = evidenceSourceUploader();
    $project = evidenceSourceProject($leader);

    $this->actingAs($leader)
        ->post(route('projects.evidence.store', $project), evidenceSourcePayload($project))
        ->assertRedirect();

    $fileEvidence = ProjectEvidence::query()->firstOrFail();
    expect($fileEvidence->source_type)->toBe('FILE')
        ->and($fileEvidence->scan_status)->toBe('PENDING');
    Queue::assertPushed(InspectProjectEvidence::class);

    $this->post(route('projects.evidence.store', $project), evidenceSourcePayload($project, [
        'source_type' => 'LINK',
        'file' => null,
        'url' => 'https://example.com/panduan-qcc',
    ]))->assertRedirect();

    $linkEvidence = ProjectEvidence::query()->latest('id')->firstOrFail();
    expect($linkEvidence->source_type)->toBe('LINK')
        ->and($linkEvidence->url)->toBe('https://example.com/panduan-qcc')
        ->and($linkEvidence->storage_key)->toBeNull()
        ->and($linkEvidence->scan_status)->toBe('NOT_REQUIRED')
        ->and($linkEvidence->processing_status)->toBe('NOT_REQUIRED')
        ->and($linkEvidence->extraction_status)->toBe('NOT_REQUIRED')
        ->and($linkEvidence->version_number)->toBe(2)
        ->and($linkEvidence->supersedes_id)->toBe($fileEvidence->id)
        ->and($linkEvidence->links()->count())->toBe(1)
        ->and(AuditLog::query()->where('event', 'evidence.created')->where('subject_id', $linkEvidence->id)->exists())->toBeTrue();
    expect(Queue::pushed(InspectProjectEvidence::class)->count())->toBe(1);
});

test('AI chat evidence requires summary and scoring receives summary without fetching its URL', function () {
    Storage::fake('evidence_private');
    Queue::fake();
    $leader = evidenceSourceUploader();
    $project = evidenceSourceProject($leader);

    $this->actingAs($leader)
        ->post(route('projects.evidence.store', $project), evidenceSourcePayload($project, [
            'source_type' => 'AI_CHAT_LINK',
            'file' => null,
            'url' => 'https://chat.example.com/conversation/private-token',
        ]))
        ->assertSessionHasErrors('summary');

    $this->post(route('projects.evidence.store', $project), evidenceSourcePayload($project, [
        'source_type' => 'AI_CHAT_LINK',
        'file' => null,
        'url' => 'https://chat.example.com/conversation/private-token',
        'summary' => 'Tim meminta analisis akar masalah dan menerima saran untuk memverifikasi data proses.',
    ]))->assertRedirect();

    $evidence = ProjectEvidence::query()->firstOrFail();
    expect($evidence->scan_status)->toBe('NOT_REQUIRED')
        ->and($evidence->processing_status)->toBe('NOT_REQUIRED')
        ->and(Queue::pushed(InspectProjectEvidence::class))->toBeEmpty();

    $manifest = app(EvidenceResolver::class)->readyEvidenceFor($project->id);
    expect($manifest)->toBe([[
        'evidence_id' => $evidence->id,
        'title' => $evidence->title,
        'content' => $evidence->summary,
    ]])->and($manifest[0]['content'])->not->toContain('chat.example.com');
});

test('evidence links reject non-http schemes', function () {
    Storage::fake('evidence_private');
    Queue::fake();
    $leader = evidenceSourceUploader();
    $project = evidenceSourceProject($leader);

    foreach (['ftp://example.com/file', 'javascript:alert(1)'] as $url) {
        $this->actingAs($leader)
            ->post(route('projects.evidence.store', $project), evidenceSourcePayload($project, [
                'source_type' => 'LINK',
                'file' => null,
                'url' => $url,
            ]))
            ->assertSessionHasErrors('url');
    }

    expect(ProjectEvidence::query()->count())->toBe(0);
});

test('evidence URL uploads remain restricted to users with project access', function () {
    Storage::fake('evidence_private');
    Queue::fake();
    $leader = evidenceSourceUploader();
    $project = evidenceSourceProject($leader);
    $outsider = User::factory()->create();
    $outsider->assignRole(Role::findOrCreate('SIMer / Member', 'web'));
    $outsider->givePermissionTo(Permission::findOrCreate('evidence.upload', 'web'));

    $this->actingAs($outsider)
        ->post(route('projects.evidence.store', $project), evidenceSourcePayload($project, [
            'source_type' => 'LINK',
            'file' => null,
            'url' => 'https://example.com/unauthorized',
        ]))
        ->assertForbidden();

    expect(ProjectEvidence::query()->count())->toBe(0);
});
