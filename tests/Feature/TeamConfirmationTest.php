<?php

use App\Enums\ProposalStatus;
use App\Enums\TeamMatchingStatus;
use App\Models\AuditLog;
use App\Models\Competency;
use App\Models\MemberCompetency;
use App\Models\Organization;
use App\Models\Project;
use App\Models\TeamMatchingRun;
use App\Models\User;
use App\Services\Teams\CandidateShortlist;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function confirmReviewer(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('Owner QCC', 'web'));
    $user->givePermissionTo(Permission::findOrCreate('projects.view', 'web'), Permission::findOrCreate('projects.viewAny', 'web'));

    return $user;
}

function confirmCandidate(string $name, Competency $competency, ?Organization $organization = null): User
{
    $user = User::factory()->create([
        'name' => $name,
        'organization_id' => ($organization ?? Organization::factory()->create(['is_active' => true]))->id,
    ]);
    $user->assignRole(Role::findOrCreate('SIMer / Member', 'web'));
    MemberCompetency::factory()->create(['user_id' => $user->id, 'competency_id' => $competency->id, 'self_level' => 3]);

    return $user;
}

function readyRun(Project $project, array $suggested, ?TeamMatchingStatus $status = null): TeamMatchingRun
{
    $shortlist = app(CandidateShortlist::class);
    $snapshot = $shortlist->snapshot($project, $shortlist->for($project));

    $run = TeamMatchingRun::query()->create([
        'project_id' => $project->id,
        'requested_by' => User::factory()->create()->id,
        'status' => $status ?? TeamMatchingStatus::Ready,
        'idempotency_key' => (string) str()->uuid(),
        'candidate_snapshot_json' => $snapshot,
    ]);
    $run->forceFill(['output_json' => [
        'recommended_members' => array_map(fn (array $pick) => [
            'user_id' => $pick[0], 'proposed_role' => $pick[1], 'matched_competency_ids' => [],
            'match_reason' => 'Cocok.', 'contribution' => 'Membantu.', 'gaps' => [], 'uncertainties' => [],
        ], $suggested),
        'team_gaps' => [], 'cautions' => [], 'rule_problems' => [],
    ]])->save();

    return $run;
}

function activeMembers(Project $project): array
{
    return DB::table('project_members')->where('project_id', $project->id)->where('status', 'ACTIVE')->whereNull('left_at')
        ->orderBy('user_id')->get(['user_id', 'role_in_project'])->map(fn ($row) => [(int) $row->user_id, $row->role_in_project])->all();
}

/** @param list<array{0:int,1:string}> $team */
function teamPayload(TeamMatchingRun $run, array $team): array
{
    return ['run_id' => $run->id, 'members' => array_map(fn ($pick) => ['user_id' => $pick[0], 'role_in_project' => $pick[1]], $team)];
}

beforeEach(function () {
    $this->project = Project::factory()->create();
    $this->skill = Competency::factory()->create(['is_active' => true]);
    DB::table('project_requirements')->insert(['project_id' => $this->project->id, 'competency_id' => $this->skill->id, 'created_at' => now(), 'updated_at' => now()]);
    $this->a = confirmCandidate('Satu', $this->skill);
    $this->b = confirmCandidate('Dua', $this->skill);
    $this->c = confirmCandidate('Tiga', $this->skill);
    $this->run = readyRun($this->project, [[$this->a->id, 'LEADER'], [$this->b->id, 'MEMBER']]);
    $this->reviewer = confirmReviewer();
    $this->url = route('proposals.team.confirm', $this->project);
});

it('confirms the AI suggestion as the active team and records who did it', function () {
    $this->actingAs($this->reviewer)->post($this->url, teamPayload($this->run, [[$this->a->id, 'LEADER'], [$this->b->id, 'MEMBER']]))
        ->assertRedirect()->assertSessionHasNoErrors();

    $run = $this->run->fresh();
    $log = AuditLog::query()->where('event', 'team_matching.confirmed')->first();

    expect(activeMembers($this->project))->toBe([[$this->a->id, 'LEADER'], [$this->b->id, 'MEMBER']])
        ->and($run->confirmed_by)->toBe($this->reviewer->id)->and($run->confirmed_at)->not->toBeNull()
        ->and($log->actor_id)->toBe($this->reviewer->id)
        ->and($log->after_json['changed_from_ai'])->toBeFalse();
});

it('confirms a team the person changed by hand and says so in the audit log', function () {
    $this->actingAs($this->reviewer)->post($this->url, teamPayload($this->run, [[$this->b->id, 'LEADER'], [$this->c->id, 'MEMBER']]))
        ->assertSessionHasNoErrors();

    expect(activeMembers($this->project))->toEqualCanonicalizing([[$this->b->id, 'LEADER'], [$this->c->id, 'MEMBER']])
        ->and(AuditLog::query()->where('event', 'team_matching.confirmed')->first()->after_json['changed_from_ai'])->toBeTrue();
});

it('refuses a team that breaks the rules and keeps everything as it was', function (Closure $makeTeam) {
    $response = $this->actingAs($this->reviewer)->post($this->url, teamPayload($this->run, $makeTeam($this)));

    $response->assertSessionHasErrors();

    expect(activeMembers($this->project))->toBe([])
        ->and($this->run->fresh()->confirmed_at)->toBeNull()
        ->and(AuditLog::query()->where('event', 'team_matching.confirmed')->count())->toBe(0);
})->with([
    'two leaders' => [fn ($t) => [[$t->a->id, 'LEADER'], [$t->b->id, 'LEADER']]],
    'no leader' => [fn ($t) => [[$t->a->id, 'MEMBER'], [$t->b->id, 'MEMBER']]],
    'one LOB' => [function ($t) {
        $same = Organization::factory()->create(['is_active' => true]);
        $x = confirmCandidate('Satu Lob', $t->skill, $same);
        $y = confirmCandidate('Dua Lob', $t->skill, $same);
        $t->run->forceFill(['candidate_snapshot_json' => app(CandidateShortlist::class)->snapshot($t->project, app(CandidateShortlist::class)->for($t->project))])->save();

        return [[$x->id, 'LEADER'], [$y->id, 'MEMBER']];
    }],
    'the same person twice' => [fn ($t) => [[$t->a->id, 'LEADER'], [$t->a->id, 'MEMBER']]],
]);

it('only accepts people from the shortlist the run was built on', function () {
    $outsider = confirmCandidate('Orang Baru', $this->skill);

    $this->actingAs($this->reviewer)->post($this->url, teamPayload($this->run, [[$this->a->id, 'LEADER'], [$outsider->id, 'MEMBER']]))
        ->assertSessionHasErrors('members');

    expect(activeMembers($this->project))->toBe([]);
});

it('does not take a run that is not ready, belongs to another project or was already used', function () {
    $team = [[$this->a->id, 'LEADER'], [$this->b->id, 'MEMBER']];

    $this->actingAs($this->reviewer)->post($this->url, teamPayload($this->run, $team))->assertSessionHasNoErrors();
    $this->actingAs($this->reviewer)->post($this->url, teamPayload($this->run, $team))->assertSessionHasErrors('team');

    $queued = readyRun($this->project, [], TeamMatchingStatus::Queued);
    $this->actingAs($this->reviewer)->post($this->url, teamPayload($queued, $team))->assertSessionHasErrors('team');

    $other = readyRun(Project::factory()->create(), []);
    $this->actingAs($this->reviewer)->post($this->url, teamPayload($other, $team))->assertSessionHasErrors('team');
});

it('only confirms a team while the proposal is still a draft', function () {
    $this->project->forceFill(['proposal_status' => ProposalStatus::Submitted])->save();

    $this->actingAs($this->reviewer)->post($this->url, teamPayload($this->run, [[$this->a->id, 'LEADER'], [$this->b->id, 'MEMBER']]))
        ->assertSessionHasErrors('team');

    expect(activeMembers($this->project))->toBe([]);
});

it('is closed to people who may not search candidates', function () {
    $member = User::factory()->create();
    $member->assignRole(Role::findOrCreate('SIMer / Member', 'web'));
    $member->givePermissionTo(Permission::findOrCreate('projects.view', 'web'));

    $this->actingAs($member)->post($this->url, teamPayload($this->run, [[$this->a->id, 'LEADER'], [$this->b->id, 'MEMBER']]))->assertForbidden();
    auth()->logout();
    $this->post($this->url, [])->assertRedirect(route('login'));
});

it('hands the page what it needs to review, change and confirm', function () {
    $this->actingAs($this->reviewer)->get(route('proposals.candidates', $this->project))
        ->assertInertia(fn (Assert $page) => $page
            ->where('matching.status', 'READY')
            ->where('matching.confirmed_at', null)
            ->has('matching.members', 2)
            ->where('matching.members.0.organization_id', $this->a->organization_id)
            ->has('matching.members.0.competencies', 1)
            ->has('candidates', 3)
            ->has('candidates.0.organization_id')
            ->where('team', []));

    $this->actingAs($this->reviewer)->post($this->url, teamPayload($this->run, [[$this->a->id, 'LEADER'], [$this->b->id, 'MEMBER']]));

    $this->actingAs($this->reviewer)->get(route('proposals.candidates', $this->project))
        ->assertInertia(fn (Assert $page) => $page
            ->where('matching.confirmed_at', fn ($value) => $value !== null)
            ->has('team', 2)
            ->where('team.0.role', 'LEADER')
            ->where('team.0.name', 'Satu'));
});

it('refuses a run that a newer one has replaced', function () {
    $newer = readyRun($this->project, [[$this->a->id, 'LEADER'], [$this->b->id, 'MEMBER']]);

    $this->actingAs($this->reviewer)->post($this->url, teamPayload($this->run, [[$this->a->id, 'LEADER'], [$this->b->id, 'MEMBER']]))
        ->assertSessionHasErrors('team');

    expect(activeMembers($this->project))->toBe([]);

    $this->actingAs($this->reviewer)->post($this->url, teamPayload($newer, [[$this->a->id, 'LEADER'], [$this->b->id, 'MEMBER']]))
        ->assertSessionHasNoErrors();

    expect(activeMembers($this->project))->toHaveCount(2);
});

it('replaces an earlier team when a later run is confirmed and keeps one leader', function () {
    $this->actingAs($this->reviewer)->post($this->url, teamPayload($this->run, [[$this->a->id, 'LEADER'], [$this->b->id, 'MEMBER']]));

    $second = readyRun($this->project, [[$this->b->id, 'LEADER'], [$this->c->id, 'MEMBER']]);
    $this->actingAs($this->reviewer)->post($this->url, teamPayload($second, [[$this->b->id, 'LEADER'], [$this->c->id, 'MEMBER']]))
        ->assertSessionHasNoErrors();

    expect(activeMembers($this->project))->toEqualCanonicalizing([[$this->b->id, 'LEADER'], [$this->c->id, 'MEMBER']])
        ->and(DB::table('project_members')->where('user_id', $this->a->id)->value('status'))->toBe('REMOVED');
});

it('shows the current team with its LOB and the required competencies it holds', function () {
    $this->actingAs($this->reviewer)->post($this->url, teamPayload($this->run, [[$this->a->id, 'LEADER'], [$this->b->id, 'MEMBER']]));

    $this->actingAs($this->reviewer)->get(route('proposals.candidates', $this->project))
        ->assertInertia(fn (Assert $page) => $page
            ->where('team.0.organization_id', $this->a->organization_id)
            ->has('team.0.competencies', 1)
            ->where('team.0.competencies.0.competency_id', $this->skill->id));
});
