<?php

use App\Models\Competency;
use App\Models\MemberCompetency;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use App\Services\Teams\CandidateShortlist;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function requireSkill(Project $project, Competency $competency, ?int $minimum = null): void
{
    DB::table('project_requirements')->insert([
        'project_id' => $project->id,
        'competency_id' => $competency->id,
        'minimum_level' => $minimum,
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function candidate(string $name, Competency $competency, int $self = 3, ?int $verified = null, array $attributes = [], string $role = 'SIMer / Member'): User
{
    $user = User::factory()->create(array_merge([
        'name' => $name,
        'organization_id' => Organization::factory()->create(['is_active' => true])->id,
        'email' => strtolower(str_replace(' ', '.', $name)).'@rahasia.test',
    ], $attributes));
    $user->assignRole(Role::findOrCreate($role, 'web'));
    MemberCompetency::factory()->create([
        'user_id' => $user->id,
        'competency_id' => $competency->id,
        'self_level' => $self,
        'verified_level' => $verified,
    ]);

    return $user;
}

function shortlistFor(Project $project): array
{
    return app(CandidateShortlist::class)->for($project);
}

function shortlistNames(Project $project): array
{
    return array_column(shortlistFor($project)['candidates'], 'name');
}

beforeEach(function () {
    $this->project = Project::factory()->create();
    $this->skill = Competency::factory()->create();
    requireSkill($this->project, $this->skill);
});

test('only eligible people who hold a required competency are listed', function () {
    $other = Competency::factory()->create();

    candidate('Cocok Satu', $this->skill);
    candidate('Skill Lain', $other);
    candidate('Nonaktif', $this->skill, attributes: ['is_active' => false]);
    candidate('Tidak Tersedia', $this->skill, attributes: ['availability_status' => 'UNAVAILABLE']);
    candidate('Tersedia', $this->skill, attributes: ['availability_status' => 'AVAILABLE']);
    candidate('Org Nonaktif', $this->skill, attributes: ['organization_id' => Organization::factory()->create(['is_active' => false])->id]);
    candidate('Tanpa Org', $this->skill, attributes: ['organization_id' => null]);
    candidate('Seorang Mentor', $this->skill, role: 'Mentor');

    expect(shortlistNames($this->project))->toEqualCanonicalizing(['Cocok Satu', 'Tersedia']);
});

test('people already on the project team are left out', function () {
    $member = candidate('Sudah Anggota', $this->skill);
    candidate('Belum Anggota', $this->skill);

    DB::table('project_members')->insert([
        'project_id' => $this->project->id, 'user_id' => $member->id, 'role_in_project' => 'MEMBER',
        'status' => 'ACTIVE', 'created_at' => now(), 'updated_at' => now(),
    ]);

    expect(shortlistNames($this->project))->toBe(['Belum Anggota']);
});

test('the minimum level counts the verified level before the self level', function () {
    $strict = Project::factory()->create();
    requireSkill($strict, $this->skill, minimum: 3);

    candidate('Mandiri Tinggi Verifikasi Rendah', $this->skill, self: 4, verified: 2);
    candidate('Mandiri Rendah Verifikasi Tinggi', $this->skill, self: 1, verified: 3);
    candidate('Mandiri Cukup', $this->skill, self: 3);

    expect(shortlistNames($strict))->toEqualCanonicalizing(['Mandiri Rendah Verifikasi Tinggi', 'Mandiri Cukup']);
});

test('better matches come first and the list is bounded', function () {
    $second = Competency::factory()->create();
    requireSkill($this->project, $second);

    $partial = candidate('Sebagian', $this->skill, self: 4);
    $full = candidate('Lengkap', $this->skill, self: 2);
    MemberCompetency::factory()->create(['user_id' => $full->id, 'competency_id' => $second->id, 'self_level' => 2]);

    expect(shortlistNames($this->project))->toBe(['Lengkap', 'Sebagian'])
        ->and(app(CandidateShortlist::class)->for($this->project, 1)['candidates'])->toHaveCount(1);
});

test('a project without requirements has no candidates', function () {
    candidate('Siapa Saja', $this->skill);

    expect(shortlistFor(Project::factory()->create())['candidates'])->toBe([]);
});

test('neither the shortlist nor the snapshot carries private contacts', function () {
    candidate('Rahasia Orang', $this->skill, attributes: [
        'work_contact' => '0811-WORK-1111',
        'personal_contact' => '0812-PRIBADI-2222',
        'sim_id' => 'SIM-PRIVATE-9',
    ]);

    $shortlist = shortlistFor($this->project);
    $snapshot = app(CandidateShortlist::class)->snapshot($this->project, $shortlist);
    $encoded = json_encode([$shortlist, $snapshot]);

    expect($encoded)->not->toContain('rahasia.orang@rahasia.test')
        ->and($encoded)->not->toContain('0811-WORK-1111')
        ->and($encoded)->not->toContain('0812-PRIBADI-2222')
        ->and($encoded)->not->toContain('SIM-PRIVATE-9')
        ->and(json_encode($snapshot))->not->toContain('Rahasia Orang')
        ->and($snapshot['candidates'][0])->toHaveKeys(['candidate_user_id', 'organization_id', 'availability_status', 'competencies', 'qcc_experience'])
        ->and($snapshot['candidates'][0]['qcc_experience'])->toBe([
            'projects_count' => 0,
            'leader_projects_count' => 0,
            'completed_projects_count' => 0,
        ]);
});

test('the AI snapshot includes only aggregate prior QCC experience counts', function () {
    $member = candidate('Kandidat Berpengalaman', $this->skill);
    $completedProject = Project::factory()->create();
    $ongoingProject = Project::factory()->create();

    DB::table('project_members')->insert([
        [
            'project_id' => $completedProject->id,
            'user_id' => $member->id,
            'role_in_project' => 'LEADER',
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ],
        [
            'project_id' => $ongoingProject->id,
            'user_id' => $member->id,
            'role_in_project' => 'MEMBER',
            'status' => 'ACTIVE',
            'created_at' => now(),
            'updated_at' => now(),
        ],
    ]);

    foreach (range(1, 8) as $stepNumber) {
        DB::table('qcc_steps')->insert([
            'project_id' => $completedProject->id,
            'step_number' => $stepNumber,
            'status' => 'APPROVED',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    $snapshot = app(CandidateShortlist::class)->snapshot($this->project, shortlistFor($this->project));
    $candidateSnapshot = collect($snapshot['candidates'])->firstWhere('candidate_user_id', $member->id);

    expect($candidateSnapshot['qcc_experience'])->toBe([
        'projects_count' => 2,
        'leader_projects_count' => 1,
        'completed_projects_count' => 1,
    ])->and($candidateSnapshot)->not->toHaveKeys(['name', 'email', 'position', 'organization']);
});

test('the shortlist takes a fixed number of queries however many candidates there are', function () {
    foreach (range(1, 15) as $number) {
        candidate("Orang {$number}", $this->skill);
    }

    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });

    shortlistFor($this->project);

    expect($queries)->toBeLessThanOrEqual(5);
});

test('the ranking query uses indexes on the big tables', function () {
    candidate('Satu Orang', $this->skill);

    $query = app(CandidateShortlist::class)->rankedQuery($this->project);
    $plan = collect(DB::select('EXPLAIN QUERY PLAN '.$query->toSql(), $query->getBindings()))->pluck('detail')->implode(' | ');

    expect($plan)->toContain('USING')
        ->and($plan)->not->toMatch('/SCAN member_competencies(?! USING)/');
});

function candidateSearcher(string $role, bool $viewAny): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate($role, 'web'));
    $user->givePermissionTo(Permission::findOrCreate('projects.view', 'web'));

    if ($viewAny) {
        $user->givePermissionTo(Permission::findOrCreate('projects.viewAny', 'web'));
    }

    return $user;
}

test('an owner opens the candidate page for any project', function () {
    candidate('Kandidat Tampil', $this->skill);

    $this->actingAs(candidateSearcher('Owner QCC', true))
        ->get(route('proposals.candidates', $this->project))
        ->assertInertia(fn (Assert $page) => $page
            ->component('proposals/candidates')
            ->has('requirements', 1)
            ->has('candidates', 1)
            ->where('candidates.0.name', 'Kandidat Tampil')
            ->missing('candidates.0.email'));
});

test('a team leader can search only for a project they proposed', function () {
    $leader = candidateSearcher('Team Leader', false);
    $own = Project::factory()->create(['proposer_id' => $leader->id]);

    $this->actingAs($leader)->get(route('proposals.candidates', $own))->assertOk();
    $this->actingAs($leader)->get(route('proposals.candidates', $this->project))->assertForbidden();
});

test('members and guests cannot search candidates', function () {
    $this->get(route('proposals.candidates', $this->project))->assertRedirect(route('login'));

    $member = candidateSearcher('SIMer / Member', false);
    $own = Project::factory()->create(['proposer_id' => $member->id]);

    $this->actingAs($member)->get(route('proposals.candidates', $own))->assertForbidden();
});
