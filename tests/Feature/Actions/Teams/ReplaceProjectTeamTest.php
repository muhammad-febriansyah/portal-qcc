<?php

use App\Actions\Teams\ReplaceProjectTeam;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

function teamPerson(?Organization $organization = null, array $attributes = []): User
{
    return User::factory()->create(array_merge([
        'organization_id' => ($organization ?? Organization::factory()->create(['is_active' => true]))->id,
    ], $attributes));
}

/** @return list<array{user_id: int, role_in_project: string}> */
function validTeam(int $size = 3): array
{
    $first = Organization::factory()->create(['is_active' => true]);
    $second = Organization::factory()->create(['is_active' => true]);

    return collect(range(1, $size))->map(fn (int $n): array => [
        'user_id' => teamPerson($n % 2 === 0 ? $first : $second)->id,
        'role_in_project' => $n === 1 ? 'LEADER' : 'MEMBER',
    ])->all();
}

function activeTeam(Project $project): array
{
    return DB::table('project_members')->where('project_id', $project->id)->where('status', 'ACTIVE')->whereNull('left_at')
        ->orderBy('user_id')->get(['user_id', 'role_in_project'])
        ->map(fn ($row) => [(int) $row->user_id, $row->role_in_project])->all();
}

beforeEach(function () {
    $this->project = Project::factory()->create();
    $this->replace = app(ReplaceProjectTeam::class);
});

it('stores a valid team as active members', function () {
    $team = validTeam(5);

    $this->replace->handle($this->project, $team);

    expect(activeTeam($this->project))->toEqualCanonicalizing(array_map(fn ($m) => [$m['user_id'], $m['role_in_project']], $team));
});

it('refuses a team that breaks a rule and leaves the current team untouched', function (callable $makeInvalid) {
    $good = validTeam(3);
    $this->replace->handle($this->project, $good);

    expect(fn () => $this->replace->handle($this->project, $makeInvalid()))->toThrow(ValidationException::class)
        ->and(activeTeam($this->project))->toEqualCanonicalizing(array_map(fn ($m) => [$m['user_id'], $m['role_in_project']], $good));
})->with([
    'six people' => [fn () => validTeam(6)],
    'no leader' => [fn () => array_map(fn ($m) => ['role_in_project' => 'MEMBER'] + $m, validTeam(3))],
    'one LOB' => [function () {
        $organization = Organization::factory()->create(['is_active' => true]);

        return [
            ['user_id' => teamPerson($organization)->id, 'role_in_project' => 'LEADER'],
            ['user_id' => teamPerson($organization)->id, 'role_in_project' => 'MEMBER'],
        ];
    }],
    'the same person twice' => [function () {
        $team = validTeam(2);

        return [...$team, $team[1]];
    }],
]);

it('refuses people who are unknown or inactive', function () {
    $team = validTeam(2);
    $team[1]['user_id'] = teamPerson(attributes: ['is_active' => false])->id;

    expect(fn () => $this->replace->handle($this->project, $team))->toThrow(ValidationException::class, 'tidak ditemukan atau sudah nonaktif')
        ->and(activeTeam($this->project))->toBe([]);
});

it('uses the LOB from the database, never one sent by the caller', function () {
    $organization = Organization::factory()->create(['is_active' => true]);
    $team = [
        ['user_id' => teamPerson($organization)->id, 'role_in_project' => 'LEADER', 'organization_id' => 1],
        ['user_id' => teamPerson($organization)->id, 'role_in_project' => 'MEMBER', 'organization_id' => 2],
    ];

    expect(fn () => $this->replace->handle($this->project, $team))->toThrow(ValidationException::class, 'minimal 2 LOB');
});

it('moves people out, keeps the ones who stay and lets a returning person back in', function () {
    [$leader, $second, $third] = validTeam(3);
    $this->replace->handle($this->project, [$leader, $second, $third]);

    $joinedAt = DB::table('project_members')->where('user_id', $leader['user_id'])->value('joined_at');

    $this->replace->handle($this->project, [$leader, $second]);

    expect(DB::table('project_members')->where('user_id', $third['user_id'])->first())
        ->status->toBe('REMOVED')->left_at->not->toBeNull()
        ->and(DB::table('project_members')->where('user_id', $leader['user_id'])->value('joined_at'))->toBe($joinedAt);

    $this->replace->handle($this->project, [$leader, $second, $third]);

    expect(activeTeam($this->project))->toHaveCount(3)
        ->and(DB::table('project_members')->where('project_id', $this->project->id)->count())->toBe(3);
});

it('lets the leader change hands', function () {
    [$leader, $second] = validTeam(2);
    $this->replace->handle($this->project, [$leader, $second]);

    $this->replace->handle($this->project, [
        ['user_id' => $leader['user_id'], 'role_in_project' => 'MEMBER'],
        ['user_id' => $second['user_id'], 'role_in_project' => 'LEADER'],
    ]);

    expect(activeTeam($this->project))->toEqualCanonicalizing([[$leader['user_id'], 'MEMBER'], [$second['user_id'], 'LEADER']]);
});

it('keeps the team valid when changes arrive one after the other, valid or not', function () {
    $team = validTeam(5);
    $this->replace->handle($this->project, $team);

    $sixth = ['user_id' => teamPerson()->id, 'role_in_project' => 'MEMBER'];

    expect(fn () => $this->replace->handle($this->project, [...$team, $sixth]))->toThrow(ValidationException::class);

    $swapped = [...array_slice($team, 0, 4), $sixth];
    $this->replace->handle($this->project, $swapped);

    expect(activeTeam($this->project))->toHaveCount(5)
        ->and(collect(activeTeam($this->project))->where(1, 'LEADER'))->toHaveCount(1);
});

it('locks the project row before it reads the team', function () {
    $statements = [];
    DB::listen(function ($query) use (&$statements) {
        $statements[] = strtolower($query->sql);
    });

    $this->replace->handle($this->project, validTeam(2));

    $lockAt = collect($statements)->search(fn ($sql) => str_contains($sql, 'from "projects"') || str_contains($sql, 'from `projects`'));
    $firstWrite = collect($statements)->search(fn ($sql) => str_starts_with($sql, 'insert into "project_members"') || str_starts_with($sql, 'insert into `project_members`'));

    expect($lockAt)->not->toBeFalse()->and($lockAt)->toBeLessThan($firstWrite);
});
