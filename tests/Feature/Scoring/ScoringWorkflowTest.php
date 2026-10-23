<?php

use App\Actions\Scoring\ReviewScoringRun;
use App\Actions\Scoring\StartScoringRun;
use App\Enums\ScoringStatus;
use App\Jobs\RunAiScoring;
use App\Models\AuditLog;
use App\Models\ScoringRun;
use App\Models\User;
use App\Services\AuditDescriber;
use Database\Seeders\ProjectAccessSeeder;
use Database\Seeders\ScoringPermissionSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\Models\Role;

require_once __DIR__.'/helpers.php';

/**
 * A finished AI run waiting for a human decision. Scores 90, 80, 80 give 83.00.
 */
function runWaitingForReview(): ScoringRun
{
    $version = scoringRubric();
    $run = ScoringRun::factory()->create([
        'project_id' => scoringProjectId(),
        'rubric_version_id' => $version->id,
        'status' => ScoringStatus::PendingReview,
    ]);
    $result = $run->result()->create(['overall_summary' => 'Ringkasan', 'cautions_json' => [], 'ai_weighted_score' => '83.00']);

    foreach ($version->criteria as $index => $criterion) {
        $result->criterionScores()->create([
            'rubric_criterion_id' => $criterion->id,
            'ai_score' => $index === 0 ? 90 : 80,
            'rationale' => 'Alasan',
            'gaps_json' => [],
            'evidence_references_json' => [],
        ]);
    }

    return $run->load('rubricVersion.criteria');
}

test('starting a run needs the scoring.run permission', function () {
    Queue::fake();

    expect(fn () => app(StartScoringRun::class)->handle(scoringProjectId(), scoringRubric(), User::factory()->create()))
        ->toThrow(AuthorizationException::class);

    Queue::assertNothingPushed();
});

test('starting a run queues one job and records who asked', function () {
    Queue::fake();
    $owner = userWith('scoring.run', 'projects.view', 'projects.viewAny');
    $version = scoringRubric();
    $projectId = scoringProjectId();
    $this->actingAs($owner);

    $run = app(StartScoringRun::class)->handle($projectId, $version, $owner);

    expect($run->status)->toBe(ScoringStatus::EvidenceCheck)
        ->and($run->triggered_by)->toBe($owner->id);
    Queue::assertPushed(RunAiScoring::class, fn (RunAiScoring $job): bool => $job->runId === $run->id);
    expect(AuditLog::query()->where('event', 'scoring.run_requested')->where('actor_id', $owner->id)->exists())->toBeTrue();
});

test('repeating the same idempotency key returns the same run and queues nothing more', function () {
    Queue::fake();
    $owner = userWith('scoring.run', 'projects.view', 'projects.viewAny');
    $version = scoringRubric();
    $projectId = scoringProjectId();

    $first = app(StartScoringRun::class)->handle($projectId, $version, $owner, 'klik-1');
    $second = app(StartScoringRun::class)->handle($projectId, $version, $owner, 'klik-1');

    expect($second->id)->toBe($first->id)->and(ScoringRun::query()->count())->toBe(1);
    Queue::assertPushed(RunAiScoring::class, 1);
});

test('a second run is refused while one is still active for the same project and rubric', function () {
    Queue::fake();
    $owner = userWith('scoring.run', 'projects.view', 'projects.viewAny');
    $version = scoringRubric();
    $projectId = scoringProjectId();

    app(StartScoringRun::class)->handle($projectId, $version, $owner);

    expect(fn () => app(StartScoringRun::class)->handle($projectId, $version, $owner))->toThrow(ValidationException::class);
    Queue::assertPushed(RunAiScoring::class, 1);
});

test('a draft rubric, a rubric not totalling 100 and an unknown project are refused', function () {
    Queue::fake();
    $owner = userWith('scoring.run', 'projects.view', 'projects.viewAny');

    expect(fn () => app(StartScoringRun::class)->handle(scoringProjectId(), scoringRubric(status: 'draft'), $owner))->toThrow(ValidationException::class)
        ->and(fn () => app(StartScoringRun::class)->handle(scoringProjectId(), scoringRubric([30, 30]), $owner))->toThrow(ValidationException::class)
        ->and(fn () => app(StartScoringRun::class)->handle(999999, scoringRubric(), $owner))->toThrow(ValidationException::class);

    Queue::assertNothingPushed();
});

test('approving without changes makes the ai total the final score', function () {
    $run = runWaitingForReview();
    $reviewer = userWith('scoring.approve', 'projects.view', 'projects.viewAny');

    app(ReviewScoringRun::class)->approve($run, $reviewer, comment: 'Sesuai.');

    $run->refresh();
    $result = $run->result;

    expect($run->status)->toBe(ScoringStatus::Approved)
        ->and($result->final_score)->toBe('83.00')
        ->and($result->reviewer_weighted_score)->toBeNull()
        ->and($result->reviewed_by)->toBe($reviewer->id)
        ->and($result->criterionScores->pluck('reviewer_score')->filter()->all())->toBe([])
        ->and($result->criterionScores->pluck('final_score')->map(fn ($score) => (string) $score)->all())->toBe(['90.00', '80.00', '80.00']);
    expect(AuditLog::query()->where('event', 'scoring.approved')->count())->toBe(1)
        ->and(AuditLog::query()->where('event', 'scoring.overridden')->count())->toBe(0);
});

test('a reviewer score replaces the ai score, needs a reason, and the total is recalculated', function () {
    $run = runWaitingForReview();
    $reviewer = userWith('scoring.approve', 'projects.view', 'projects.viewAny');
    $firstCriterionId = $run->rubricVersion->criteria->first()->id;

    expect(fn () => app(ReviewScoringRun::class)->approve($run, $reviewer, [$firstCriterionId => 60]))
        ->toThrow(ValidationException::class);
    expect($run->refresh()->status)->toBe(ScoringStatus::PendingReview);

    app(ReviewScoringRun::class)->approve($run, $reviewer, [$firstCriterionId => 60], 'Target belum terverifikasi.');

    $result = $run->refresh()->result;
    $changed = $result->criterionScores->firstWhere('rubric_criterion_id', $firstCriterionId);

    expect($result->ai_weighted_score)->toBe('83.00')
        ->and($result->reviewer_weighted_score)->toBe('74.00')
        ->and($result->final_score)->toBe('74.00')
        ->and($changed->ai_score)->toBe('90.00')
        ->and($changed->reviewer_score)->toBe('60.00')
        ->and($changed->override_reason)->toBe('Target belum terverifikasi.');

    $log = AuditLog::query()->where('event', 'scoring.overridden')->firstOrFail();
    expect($log->before_json)->toBe(['K1' => '90.00'])
        ->and($log->after_json)->toBe(['K1' => '60.00'])
        ->and($log->reason)->toBe('Target belum terverifikasi.');
});

test('the same score as the ai is not treated as an override', function () {
    $run = runWaitingForReview();
    $firstCriterionId = $run->rubricVersion->criteria->first()->id;

    app(ReviewScoringRun::class)->approve($run, userWith('scoring.approve', 'projects.view', 'projects.viewAny'), [$firstCriterionId => 90]);

    expect($run->refresh()->result->reviewer_weighted_score)->toBeNull()
        ->and(AuditLog::query()->where('event', 'scoring.overridden')->count())->toBe(0);
});

test('reviewer scores must stay between 0 and 100 and belong to this result', function () {
    $run = runWaitingForReview();
    $reviewer = userWith('scoring.approve', 'projects.view', 'projects.viewAny');
    $firstCriterionId = $run->rubricVersion->criteria->first()->id;

    expect(fn () => app(ReviewScoringRun::class)->approve($run, $reviewer, [$firstCriterionId => 101], 'x'))->toThrow(ValidationException::class)
        ->and(fn () => app(ReviewScoringRun::class)->approve($run, $reviewer, [99999 => 50], 'x'))->toThrow(ValidationException::class);

    expect($run->refresh()->status)->toBe(ScoringStatus::PendingReview);
});

test('a second approval of the same run is refused so exactly one decision exists', function () {
    $run = runWaitingForReview();
    $first = userWith('scoring.approve', 'projects.view', 'projects.viewAny');
    $second = userWith('scoring.approve', 'projects.view', 'projects.viewAny');

    app(ReviewScoringRun::class)->approve($run, $first);

    expect(fn () => app(ReviewScoringRun::class)->approve($run, $second))->toThrow(ValidationException::class);
    expect(AuditLog::query()->where('event', 'scoring.approved')->count())->toBe(1)
        ->and($run->refresh()->result->reviewed_by)->toBe($first->id);
});

test('only users with the right permission may review', function () {
    $run = runWaitingForReview();
    $viewer = userWith('scoring.view');

    expect(fn () => app(ReviewScoringRun::class)->approve($run, $viewer))->toThrow(AuthorizationException::class)
        ->and(fn () => app(ReviewScoringRun::class)->requestRevision($run, $viewer, 'x'))->toThrow(AuthorizationException::class)
        ->and(fn () => app(ReviewScoringRun::class)->approve($run, User::factory()->create()))->toThrow(AuthorizationException::class);

    expect($run->refresh()->status)->toBe(ScoringStatus::PendingReview);
});

test('scoring detail exposes criteria and evidence context only to authorized reviewers', function () {
    $this->seed(ScoringPermissionSeeder::class);
    $this->seed(ProjectAccessSeeder::class);
    $run = runWaitingForReview();
    $run->forceFill(['input_manifest_json' => [[
        'evidence_id' => 7,
        'title' => 'Laporan Step 1',
        'sha256' => hash('sha256', 'Isi laporan.'),
    ]]])->save();
    $reviewer = User::factory()->create();
    $reviewer->assignRole(Role::findOrCreate('Owner QCC', 'web'));

    $this->actingAs($reviewer)
        ->get(route('admin.scoring.show', $run))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/scoring/show')
            ->where('run.projectTitle', 'Proyek uji')
            ->where('run.result.aiScore', '83.00')
            ->where('run.result.criteria.0.code', 'K1')
            ->where('run.result.criteria.0.rationale', 'Alasan')
            ->where('run.evidence.0.title', 'Laporan Step 1')
            ->where('run.canApprove', true));

    foreach (['Mentor', 'Team Leader', 'SIMer / Member'] as $roleName) {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate($roleName, 'web'));

        $this->actingAs($user)
            ->get(route('admin.scoring.show', $run))
            ->assertForbidden();
        $this->post(route('admin.scoring.approve', $run))->assertForbidden();
        $this->post(route('admin.scoring.request-revision', $run), ['reason' => 'Lihat nilai'])->assertForbidden();
    }

    expect($run->fresh()->status)->toBe(ScoringStatus::PendingReview)
        ->and(AuditLog::query()->whereIn('event', ['scoring.approved', 'scoring.revision_requested'])->count())->toBe(0);
});

test('Mentor cannot approve scoring even when explicitly granted permission', function () {
    $run = runWaitingForReview();
    $mentor = userWith('scoring.view', 'scoring.approve', 'projects.view', 'projects.viewAny');
    $mentor->assignRole(Role::findOrCreate('Mentor', 'web'));

    $this->actingAs($mentor)
        ->post(route('admin.scoring.approve', $run))
        ->assertForbidden();

    expect($run->fresh()->status)->toBe(ScoringStatus::PendingReview)
        ->and($run->result->fresh()->final_score)->toBeNull();
});

test('requesting a revision needs a reason, keeps the ai scores and gives no final score', function () {
    $run = runWaitingForReview();
    $reviewer = userWith('scoring.review', 'projects.view', 'projects.viewAny');

    expect(fn () => app(ReviewScoringRun::class)->requestRevision($run, $reviewer, '  '))->toThrow(ValidationException::class);

    app(ReviewScoringRun::class)->requestRevision($run, $reviewer, 'Lengkapi evidence step 3.');

    $run->refresh();
    expect($run->status)->toBe(ScoringStatus::RevisionRequired)
        ->and($run->result->final_score)->toBeNull()
        ->and($run->result->review_comment)->toBe('Lengkapi evidence step 3.')
        ->and($run->result->ai_weighted_score)->toBe('83.00');
    expect(AuditLog::query()->where('event', 'scoring.revision_requested')->where('reason', 'Lengkapi evidence step 3.')->exists())->toBeTrue();
});

test('scoring approval and revision decisions notify the project team', function () {
    $run = runWaitingForReview();
    $observer = userWith('scoring.view', 'projects.viewAny');
    $reviewer = userWith('scoring.review', 'scoring.approve', 'projects.view', 'projects.viewAny');

    app(ReviewScoringRun::class)->requestRevision($run, $reviewer, 'Periksa ulang target.');
    expect($observer->notifications()->sole()->data)->toMatchArray([
        'title' => 'AI Scoring perlu revisi',
        'decision' => 'SCORING_REVISION',
    ]);

    $secondRun = runWaitingForReview();
    app(ReviewScoringRun::class)->approve($secondRun, $reviewer);

    $decisions = $observer->notifications()->get()->pluck('data')->pluck('decision')->all();

    expect($observer->notifications()->count())->toBe(2)
        ->and($decisions)->toContain('SCORING_REVISION', 'SCORING_APPROVED');
});

test('a run that is not pending review cannot be decided', function () {
    $run = runWaitingForReview();
    $run->forceFill(['status' => ScoringStatus::AiScoring])->save();

    expect(fn () => app(ReviewScoringRun::class)->approve($run, userWith('scoring.approve', 'projects.view', 'projects.viewAny')))->toThrow(ValidationException::class);
});

test('mentors, leaders and members have no scoring permission by default', function () {
    $this->seed(ScoringPermissionSeeder::class);

    foreach (['Mentor', 'Team Leader', 'SIMer / Member'] as $roleName) {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate($roleName, 'web'));

        expect($user->can('scoring.view'))->toBeFalse()->and($user->can('scoring.approve'))->toBeFalse();
    }

    $owner = User::factory()->create();
    $owner->assignRole('Owner QCC');
    $admin = User::factory()->create();
    $admin->assignRole('Super Admin');

    expect($owner->can('scoring.approve'))->toBeTrue()
        ->and($admin->can('scoring.run'))->toBeTrue()
        ->and($admin->can('scoring.approve'))->toBeFalse();
});

test('scoring events read as plain sentences in the audit log', function () {
    $run = runWaitingForReview();
    $reviewer = userWith('scoring.approve', 'projects.view', 'projects.viewAny');
    $reviewer->forceFill(['name' => 'Rina Reviewer'])->save();

    $this->actingAs($reviewer);
    app(ReviewScoringRun::class)->approve($run, $reviewer);

    $log = AuditLog::query()->where('event', 'scoring.approved')->with('actor')->firstOrFail();

    expect((new AuditDescriber)->describe($log))
        ->toBe("Rina Reviewer menyetujui nilai final Penilaian proyek #{$run->project_id}");
});
