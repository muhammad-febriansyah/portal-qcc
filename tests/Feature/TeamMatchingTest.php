<?php

use App\Actions\Teams\StartTeamMatching;
use App\Enums\TeamMatchingStatus;
use App\Jobs\RunTeamMatching;
use App\Models\Competency;
use App\Models\MemberCompetency;
use App\Models\Organization;
use App\Models\Project;
use App\Models\TeamMatchingRun;
use App\Models\User;
use App\Services\Ai\Exceptions\AiUnavailable;
use App\Services\Teams\CandidateShortlist;
use App\Services\Teams\Matching\TeamMatchingClient;
use App\Services\Teams\Matching\TeamMatchingRequestBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function aiIsReady(): void
{
    config()->set('scoring.openai.key', 'test-key');
    config()->set('scoring.openai.model', 'test-model');
}

function matchSearcher(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('Owner QCC', 'web'));
    $user->givePermissionTo(Permission::findOrCreate('projects.view', 'web'), Permission::findOrCreate('projects.viewAny', 'web'));

    return $user;
}

function matchCandidate(string $name, Competency $competency, ?Organization $organization = null): User
{
    $user = User::factory()->create([
        'name' => $name,
        'organization_id' => ($organization ?? Organization::factory()->create(['is_active' => true]))->id,
        'email' => strtolower(str_replace(' ', '.', $name)).'@rahasia.test',
    ]);
    $user->assignRole(Role::findOrCreate('SIMer / Member', 'web'));
    MemberCompetency::factory()->create(['user_id' => $user->id, 'competency_id' => $competency->id, 'self_level' => 3]);

    return $user;
}

/**
 * @param  list<array<string, mixed>>  $members
 * @return array<string, mixed>
 */
function aiAnswer(array $members): array
{
    return ['recommended_members' => $members, 'team_gaps' => [], 'cautions' => [], 'mitigations' => []];
}

function aiMember(User $user, string $role, int $competencyId): array
{
    return [
        'user_id' => $user->id,
        'proposed_role' => $role,
        'matched_competency_ids' => [$competencyId],
        'match_reason' => 'Cocok dengan kebutuhan.',
        'contribution' => 'Membantu analisis.',
        'gaps' => [],
        'uncertainties' => [],
    ];
}

function fakeMatchingClient(array|Throwable $answer): void
{
    app()->instance(TeamMatchingClient::class, new class($answer) implements TeamMatchingClient
    {
        public function __construct(private array|Throwable $answer) {}

        public function suggest(array $request): array
        {
            if ($this->answer instanceof Throwable) {
                throw $this->answer;
            }

            return ['payload' => $this->answer, 'usage' => ['total_tokens' => 10], 'model' => 'fake-model'];
        }
    });
}

function queuedRun(Project $project, array $candidateIds): TeamMatchingRun
{
    $shortlist = app(CandidateShortlist::class);
    $snapshot = $shortlist->snapshot($project, $shortlist->for($project));

    return TeamMatchingRun::query()->create([
        'project_id' => $project->id,
        'requested_by' => User::factory()->create()->id,
        'status' => TeamMatchingStatus::Queued,
        'idempotency_key' => (string) str()->uuid(),
        'candidate_snapshot_json' => $snapshot,
    ]);
}

beforeEach(function () {
    $this->project = Project::factory()->create();
    $this->skill = Competency::factory()->create();
    DB::table('project_requirements')->insert([
        'project_id' => $this->project->id, 'competency_id' => $this->skill->id,
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->a = matchCandidate('Anggota Satu', $this->skill);
    $this->b = matchCandidate('Anggota Dua', $this->skill);
});

test('starting without AI set up is refused with a clear message and queues nothing', function () {
    Queue::fake();

    expect(fn () => app(StartTeamMatching::class)->handle($this->project, matchSearcher()))
        ->toThrow(ValidationException::class, 'Integrasi AI belum diatur');

    Queue::assertNothingPushed();
    expect(TeamMatchingRun::query()->count())->toBe(0);
});

test('matching prompt includes project context and mitigation schema', function () {
    $this->project->forceFill([
        'current_situation' => 'Dokumen diverifikasi manual.',
        'improvement_plan' => 'Otomatisasi verifikasi.',
        'rca' => 'Proses belum terstandar.',
        'business_benefit' => 'Waktu proses turun.',
    ])->save();

    $shortlist = app(CandidateShortlist::class);
    $request = app(TeamMatchingRequestBuilder::class)->build($this->project, $shortlist->snapshot($this->project, $shortlist->for($this->project)));

    expect($request['input']['project'])
        ->toMatchArray([
            'current_situation' => 'Dokumen diverifikasi manual.',
            'improvement_plan' => 'Otomatisasi verifikasi.',
            'root_cause_analysis' => 'Proses belum terstandar.',
            'business_benefit' => 'Waktu proses turun.',
        ])
        ->and($request['schema']['required'])->toContain('mitigations')
        ->and($request['schema']['properties']['mitigations']['items']['properties']['type']['enum'])
        ->toBe(['COACHING', 'TRAINING', 'MENTOR']);
});

test('starting with AI ready stores the snapshot and queues the job', function () {
    aiIsReady();
    Queue::fake();

    $run = app(StartTeamMatching::class)->handle($this->project, matchSearcher());

    expect($run->status)->toBe(TeamMatchingStatus::Queued)
        ->and(array_column($run->candidate_snapshot_json['candidates'], 'candidate_user_id'))->toEqualCanonicalizing([$this->a->id, $this->b->id])
        ->and(json_encode($run->candidate_snapshot_json))->not->toContain('rahasia.test');

    Queue::assertPushed(RunTeamMatching::class, fn ($job) => $job->runId === $run->id);
});

test('a second request is refused while one is running or too recent', function () {
    aiIsReady();
    Queue::fake();
    $searcher = matchSearcher();

    app(StartTeamMatching::class)->handle($this->project, $searcher);

    expect(fn () => app(StartTeamMatching::class)->handle($this->project, $searcher))
        ->toThrow(ValidationException::class, 'masih diproses');

    TeamMatchingRun::query()->update(['status' => TeamMatchingStatus::Ready]);

    expect(fn () => app(StartTeamMatching::class)->handle($this->project, $searcher))
        ->toThrow(ValidationException::class, 'Tunggu sebentar');
});

test('there is nothing to match without requirements or candidates', function () {
    aiIsReady();

    DB::table('member_competencies')->delete();
    expect(fn () => app(StartTeamMatching::class)->handle($this->project, matchSearcher()))
        ->toThrow(ValidationException::class, 'Belum ada kandidat');

    expect(fn () => app(StartTeamMatching::class)->handle(Project::factory()->create(), matchSearcher()))
        ->toThrow(ValidationException::class, 'kebutuhan kompetensi');
});

test('a valid suggestion is stored and marked ready', function () {
    $answer = aiAnswer([aiMember($this->a, 'LEADER', $this->skill->id), aiMember($this->b, 'MEMBER', $this->skill->id)]);
    $answer['mitigations'] = [[
        'gap' => 'Pengalaman analisis data terbatas.',
        'recommendation' => 'Jadwalkan sesi coaching analisis data.',
        'type' => 'COACHING',
    ]];
    fakeMatchingClient($answer);
    $run = queuedRun($this->project, []);

    app()->call([new RunTeamMatching($run->id), 'handle']);

    $run->refresh();

    expect($run->status)->toBe(TeamMatchingStatus::Ready)
        ->and($run->output_json['rule_problems'])->toBe([])
        ->and($run->output_json['mitigations'][0]['type'])->toBe('COACHING')
        ->and($run->model)->toBe('fake-model')
        ->and($run->validated_at)->not->toBeNull();
});

test('output with missing or unknown mitigation fields is rejected', function () {
    $validMitigation = [
        'gap' => 'Pengalaman analisis data terbatas.',
        'recommendation' => 'Jadwalkan sesi coaching analisis data.',
        'type' => 'COACHING',
    ];
    $invalidMitigations = [
        [['gap' => $validMitigation['gap'], 'type' => 'COACHING']],
        [$validMitigation + ['invented_field' => 'tidak boleh ada']],
        [array_merge($validMitigation, ['type' => 'ADVICE'])],
    ];
    $members = [aiMember($this->a, 'LEADER', $this->skill->id), aiMember($this->b, 'MEMBER', $this->skill->id)];

    $missingMitigations = aiAnswer($members);
    unset($missingMitigations['mitigations']);
    $invalidAnswers = [$missingMitigations];

    foreach ($invalidMitigations as $mitigations) {
        $answer = aiAnswer($members);
        $answer['mitigations'] = $mitigations;
        $invalidAnswers[] = $answer;
    }

    foreach ($invalidAnswers as $answer) {
        fakeMatchingClient($answer);
        $run = queuedRun($this->project, []);

        app()->call([new RunTeamMatching($run->id), 'handle']);

        expect($run->fresh()->status)->toBe(TeamMatchingStatus::Failed)
            ->and($run->fresh()->error_code)->toBe('invalid_output')
            ->and($run->fresh()->output_json)->toBeNull();
    }
});

test('a made-up candidate or competency is rejected and nothing is kept', function () {
    $ghost = ['user_id' => 999999] + aiMember($this->a, 'LEADER', $this->skill->id);
    fakeMatchingClient(aiAnswer([$ghost, aiMember($this->b, 'MEMBER', $this->skill->id)]));
    $run = queuedRun($this->project, []);

    app()->call([new RunTeamMatching($run->id), 'handle']);

    expect($run->fresh()->status)->toBe(TeamMatchingStatus::Failed)
        ->and($run->fresh()->error_code)->toBe('invalid_output')
        ->and($run->fresh()->output_json)->toBeNull();

    $wrongSkill = aiMember($this->a, 'LEADER', 999999);
    fakeMatchingClient(aiAnswer([$wrongSkill, aiMember($this->b, 'MEMBER', $this->skill->id)]));
    $second = queuedRun($this->project, []);

    app()->call([new RunTeamMatching($second->id), 'handle']);

    expect($second->fresh()->error_code)->toBe('invalid_output');
});

test('a suggestion that breaks the team rules is kept but flagged so it cannot be confirmed', function () {
    $sameOrganization = Organization::factory()->create(['is_active' => true]);
    $members = collect(range(1, 6))->map(fn ($n) => matchCandidate("Banyak {$n}", $this->skill, $sameOrganization));
    fakeMatchingClient(aiAnswer($members->map(fn (User $user, int $i) => aiMember($user, $i === 0 ? 'LEADER' : 'MEMBER', $this->skill->id))->all()));
    $run = queuedRun($this->project, []);

    app()->call([new RunTeamMatching($run->id), 'handle']);

    $problems = implode(' | ', $run->fresh()->output_json['rule_problems']);

    expect($run->fresh()->status)->toBe(TeamMatchingStatus::Ready)
        ->and($problems)->toContain('maksimal 5')
        ->and($problems)->toContain('minimal 2 LOB');
});

test('two leaders or duplicates are flagged too', function () {
    fakeMatchingClient(aiAnswer([aiMember($this->a, 'LEADER', $this->skill->id), aiMember($this->b, 'LEADER', $this->skill->id), aiMember($this->b, 'MEMBER', $this->skill->id)]));
    $run = queuedRun($this->project, []);

    app()->call([new RunTeamMatching($run->id), 'handle']);

    $problems = implode(' | ', $run->fresh()->output_json['rule_problems']);

    expect($problems)->toContain('tepat 1 leader')->and($problems)->toContain('tidak boleh dipilih lebih dari satu kali');
});

test('an AI that is not configured fails the run without retrying, a busy AI is retried', function () {
    fakeMatchingClient(new AiUnavailable('belum diatur', false, 'ai_not_configured'));
    $run = queuedRun($this->project, []);

    app()->call([new RunTeamMatching($run->id), 'handle']);

    expect($run->fresh()->status)->toBe(TeamMatchingStatus::Failed)->and($run->fresh()->error_code)->toBe('ai_not_configured');

    fakeMatchingClient(new AiUnavailable('sibuk', true, 'ai_busy'));
    $busy = queuedRun($this->project, []);
    $job = new RunTeamMatching($busy->id);

    expect(fn () => app()->call([$job, 'handle']))->toThrow(AiUnavailable::class);

    $job->failed(new AiUnavailable('sibuk', true, 'ai_busy'));

    expect($busy->fresh()->status)->toBe(TeamMatchingStatus::Failed)->and($busy->fresh()->error_code)->toBe('ai_busy');
});

test('the OpenAI request uses a strict schema and carries no names or contacts', function () {
    aiIsReady();
    Http::fake(['*/responses' => Http::response([
        'model' => 'test-model',
        'usage' => ['total_tokens' => 5],
        'output' => [['content' => [['type' => 'output_text', 'text' => json_encode(aiAnswer([aiMember($this->a, 'LEADER', $this->skill->id), aiMember($this->b, 'MEMBER', $this->skill->id)]))]]]],
    ])]);
    app()->forgetInstance(TeamMatchingClient::class);
    $run = queuedRun($this->project, []);

    app()->call([new RunTeamMatching($run->id), 'handle']);

    Http::assertSent(function ($request) {
        $body = $request->data();
        $sent = json_encode($body['input']);

        return $body['text']['format']['strict'] === true
            && $body['store'] === false
            && $request->hasHeader('Authorization', 'Bearer test-key')
            && ! str_contains($sent, 'rahasia.test')
            && ! str_contains($sent, 'Anggota Satu');
    });

    expect($run->fresh()->status)->toBe(TeamMatchingStatus::Ready);
});

test('the candidate page tells the user when AI is not ready and shows the latest suggestion', function () {
    $searcher = matchSearcher();

    $this->actingAs($searcher)->get(route('proposals.candidates', $this->project))
        ->assertInertia(fn (Assert $page) => $page->where('ai.ready', false)->where('matching', null));

    aiIsReady();
    $answer = aiAnswer([aiMember($this->a, 'LEADER', $this->skill->id), aiMember($this->b, 'MEMBER', $this->skill->id)]);
    $answer['mitigations'] = [[
        'gap' => 'Keterampilan analisis data perlu diperkuat.',
        'recommendation' => 'Ikuti pelatihan analisis data dasar.',
        'type' => 'TRAINING',
    ]];
    fakeMatchingClient($answer);
    $run = queuedRun($this->project, []);
    app()->call([new RunTeamMatching($run->id), 'handle']);

    $this->actingAs($searcher)->get(route('proposals.candidates', $this->project))
        ->assertInertia(fn (Assert $page) => $page
            ->where('ai.ready', true)
            ->where('matching.status', 'READY')
            ->has('matching.members', 2)
            ->where('matching.mitigations.0.type', 'TRAINING')
            ->where('matching.members.0.name', 'Anggota Satu'));
});

test('the start endpoint reports the AI alert and is limited to people who may search candidates', function () {
    $searcher = matchSearcher();

    $this->actingAs($searcher)->post(route('proposals.matching.start', $this->project))->assertSessionHasErrors('ai');

    $member = User::factory()->create();
    $member->assignRole(Role::findOrCreate('SIMer / Member', 'web'));
    $member->givePermissionTo(Permission::findOrCreate('projects.view', 'web'));

    $this->actingAs($member)->post(route('proposals.matching.start', $this->project))->assertForbidden();
});
