<?php

use App\Models\AuditLog;
use App\Models\Competency;
use App\Models\CompetencyGroup;
use App\Models\MemberCompetency;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\AdminPermissionSeeder;
use Database\Seeders\PeopleReportPermissionSeeder;
use Database\Seeders\ProjectAccessSeeder;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function directoryViewer(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('Super Admin', 'web'));
    $user->givePermissionTo(Permission::findOrCreate('people.report', 'web'));

    return $user;
}

function competencyVerifier(string $role = 'Owner QCC'): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate($role, 'web'));
    $user->givePermissionTo(Permission::findOrCreate('people.report', 'web'));
    $user->givePermissionTo(Permission::findOrCreate('competencies.verify', 'web'));

    return $user;
}

function person(string $name, ?Organization $organization = null, string $role = 'Team Leader'): User
{
    $user = User::factory()->create(['name' => $name, 'sim_id' => 'SIM-'.strtoupper(substr(md5($name), 0, 8)), 'organization_id' => $organization?->id]);
    $user->assignRole(Role::findOrCreate($role, 'web'));

    return $user;
}

function skill(string $code, string $name, ?CompetencyGroup $group = null): Competency
{
    return Competency::factory()->create([
        'group_id' => ($group ?? CompetencyGroup::factory()->create())->id,
        'code' => $code,
        'name' => $name,
    ]);
}

function holds(User $user, Competency $competency, int $self, ?int $verified = null): MemberCompetency
{
    $row = MemberCompetency::factory()->create(['user_id' => $user->id, 'competency_id' => $competency->id, 'self_level' => $self]);

    if ($verified !== null) {
        $row->forceFill(['verified_level' => $verified, 'verification_status' => 'VERIFIED', 'verified_at' => now()])->save();
    }

    return $row;
}

test('guests are redirected to login', function () {
    $this->get(route('admin.competency-directory.index'))->assertRedirect(route('login'));
});

test('only Super Admin and Owner QCC are given access by the seeder', function () {
    $this->seed(PeopleReportPermissionSeeder::class);
    $this->seed(PeopleReportPermissionSeeder::class);

    foreach (['Super Admin', 'Owner QCC'] as $roleName) {
        $user = User::factory()->create();
        $user->assignRole($roleName);
        expect($user->can('people.report'))->toBeTrue();
    }

    foreach (['Mentor', 'Team Leader', 'SIMer / Member'] as $roleName) {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate($roleName, 'web'));
        expect($user->can('people.report'))->toBeFalse();
    }
});

test('Super Admin and Owner QCC receive competency verification permission', function () {
    $this->seed(AdminPermissionSeeder::class);
    $this->seed(ProjectAccessSeeder::class);

    foreach (['Super Admin', 'Owner QCC'] as $roleName) {
        $user = User::factory()->create();
        $user->assignRole(Role::findOrCreate($roleName, 'web'));

        expect($user->can('competencies.verify'))->toBeTrue();
    }
});

test('an authorized verifier saves verified level without changing self assessment and audits the decision', function () {
    $verifier = competencyVerifier();
    $member = person('Rina Peserta');
    $row = holds($member, skill('C091', 'Analisis Akar Masalah'), self: 4);

    $this->actingAs($verifier)
        ->post(route('admin.competency-directory.verify', $row), [
            'verified_level' => 3,
            'note' => 'Bukti menunjukkan kemampuan Proficient.',
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $row->refresh();
    $audit = AuditLog::query()->where('event', 'member_competency.verified')->sole();

    expect($row->self_level)->toBe(4)
        ->and($row->verified_level)->toBe(3)
        ->and($row->verified_by)->toBe($verifier->id)
        ->and($row->verified_at)->not->toBeNull()
        ->and($row->verification_status)->toBe('VERIFIED')
        ->and($row->verification_note)->toBe('Bukti menunjukkan kemampuan Proficient.')
        ->and($audit->actor_id)->toBe($verifier->id)
        ->and($audit->reason)->toBe('Bukti menunjukkan kemampuan Proficient.');
});

test('a competency owner cannot verify or reject their own assessment', function () {
    $owner = competencyVerifier();
    $row = holds($owner, skill('C099', 'Analisis Penyebab'), self: 3);

    $this->actingAs($owner)
        ->post(route('admin.competency-directory.verify', $row), ['verified_level' => 2])
        ->assertForbidden();

    $this->post(route('admin.competency-directory.reject', $row), ['reason' => 'Perlu bukti.'])
        ->assertForbidden();

    expect($row->fresh()->verification_status)->toBeNull()
        ->and($row->fresh()->verified_by)->toBeNull()
        ->and(AuditLog::query()->whereIn('event', ['member_competency.verified', 'member_competency.rejected'])->count())->toBe(0);
});

test('rejection requires reason and records the request for improvement', function () {
    $verifier = competencyVerifier();
    $row = holds(person('Dimas Anggota'), skill('C092', 'Komunikasi Data'), self: 2);
    $this->actingAs($verifier);

    $this->post(route('admin.competency-directory.reject', $row), [])
        ->assertSessionHasErrors(['reason' => 'Alasan penolakan wajib diisi.']);

    expect($row->fresh()->verification_status)->toBeNull();

    $this->post(route('admin.competency-directory.reject', $row), [
        'reason' => 'Tambahkan contoh penerapan kompetensi pada proyek.',
    ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $row->refresh();
    $audit = AuditLog::query()->where('event', 'member_competency.rejected')->sole();

    expect($row->verification_status)->toBe('REJECTED')
        ->and($row->verification_note)->toBe('Tambahkan contoh penerapan kompetensi pada proyek.')
        ->and($row->verified_by)->toBe($verifier->id)
        ->and($audit->actor_id)->toBe($verifier->id)
        ->and($audit->reason)->toBe('Tambahkan contoh penerapan kompetensi pada proyek.');
});

test('a user without competencies.verify cannot verify or reject member competency', function () {
    $viewer = directoryViewer();
    $row = holds(person('Salsa Anggota'), skill('C093', 'Pemecahan Masalah'), self: 3);
    $this->actingAs($viewer);

    $this->post(route('admin.competency-directory.verify', $row), ['verified_level' => 3])
        ->assertForbidden();
    $this->post(route('admin.competency-directory.reject', $row), ['reason' => 'Perlu bukti tambahan.'])
        ->assertForbidden();

    expect($row->fresh()->self_level)->toBe(3)
        ->and($row->fresh()->verified_level)->toBeNull()
        ->and(AuditLog::query()->whereIn('event', ['member_competency.verified', 'member_competency.rejected'])->count())->toBe(0);
});

test('people without the permission are refused', function () {
    $this->actingAs(User::factory()->create())->get(route('admin.competency-directory.index'))->assertForbidden();
});

test('by competency shows who holds it with both levels apart', function () {
    $org = Organization::factory()->create(['name' => 'DIV IT']);
    $analysis = skill('C001', 'Analisis Data');
    holds(person('Budi Santoso', $org), $analysis, self: 4, verified: 2);
    holds(person('Siti Aminah', $org), $analysis, self: 3);

    $this->actingAs(directoryViewer())
        ->get(route('admin.competency-directory.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('admin/competency-directory/index')
            ->where('filters.view', 'competency')
            ->has('rows.data', 2)
            ->where('rows.data.0.name', 'Siti Aminah')
            ->where('rows.data.1.name', 'Budi Santoso')
            ->where('rows.data.1.self_level', 4)
            ->where('rows.data.1.verified_level', 2)
            ->where('rows.data.1.organization', 'DIV IT')
            ->where('rows.data.1.role', 'Team Leader'));
});

test('the competency, group, organization and search filters narrow the list', function () {
    $itOrg = Organization::factory()->create();
    $hrOrg = Organization::factory()->create();
    $groupA = CompetencyGroup::factory()->create();
    $groupB = CompetencyGroup::factory()->create();
    $analysis = skill('C001', 'Analisis Data', $groupA);
    $negotiation = skill('C002', 'Negosiasi', $groupB);
    holds(person('Budi', $itOrg), $analysis, 3);
    holds(person('Citra', $hrOrg), $analysis, 3);
    holds(person('Dewi', $hrOrg), $negotiation, 3);

    $this->actingAs(directoryViewer());

    $this->get(route('admin.competency-directory.index', ['competency' => (string) $negotiation->id]))
        ->assertInertia(fn (Assert $page) => $page->has('rows.data', 1)->where('rows.data.0.name', 'Dewi'));
    $this->get(route('admin.competency-directory.index', ['group' => (string) $groupA->id]))
        ->assertInertia(fn (Assert $page) => $page->has('rows.data', 2));
    $this->get(route('admin.competency-directory.index', ['organization' => (string) $hrOrg->id]))
        ->assertInertia(fn (Assert $page) => $page->has('rows.data', 2));
    $this->get(route('admin.competency-directory.index', ['search' => 'SIM-'.strtoupper(substr(md5('Budi'), 0, 8))]))
        ->assertInertia(fn (Assert $page) => $page->has('rows.data', 1)->where('rows.data.0.name', 'Budi'));
});

test('the minimum level counts the verified level first and verified only hides unverified claims', function () {
    $analysis = skill('C001', 'Analisis Data');
    holds(person('Expert Sendiri'), $analysis, self: 4);
    holds(person('Verifikasi Rendah'), $analysis, self: 4, verified: 2);
    holds(person('Verifikasi Tinggi'), $analysis, self: 2, verified: 3);

    $this->actingAs(directoryViewer());

    $this->get(route('admin.competency-directory.index', ['min_level' => 3]))
        ->assertInertia(fn (Assert $page) => $page
            ->has('rows.data', 2)
            ->where('rows.data', fn ($rows) => collect($rows)->pluck('name')->sort()->values()->all() === ['Expert Sendiri', 'Verifikasi Tinggi']));

    $this->get(route('admin.competency-directory.index', ['verified_only' => 1]))
        ->assertInertia(fn (Assert $page) => $page->has('rows.data', 2));

    $this->get(route('admin.competency-directory.index', ['min_level' => 5]))->assertSessionHasErrors('min_level');
});

test('deactivated accounts are left out', function () {
    $analysis = skill('C001', 'Analisis Data');
    $gone = person('Sudah Nonaktif');
    holds($gone, $analysis, 3);
    $gone->forceFill(['is_active' => false])->save();
    holds(person('Masih Aktif'), $analysis, 3);

    $this->actingAs(directoryViewer())
        ->get(route('admin.competency-directory.index'))
        ->assertInertia(fn (Assert $page) => $page->has('rows.data', 1)->where('rows.data.0.name', 'Masih Aktif'));
});

test('by member counts competencies and verified ones, and can chase people who have not filled in', function () {
    $analysis = skill('C001', 'Analisis Data');
    $negotiation = skill('C002', 'Negosiasi');
    $full = person('Banyak Isi');
    holds($full, $analysis, 3, verified: 3);
    holds($full, $negotiation, 2);
    person('Belum Isi');

    $this->actingAs(directoryViewer());

    $this->get(route('admin.competency-directory.index', ['view' => 'member', 'sort' => 'name']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('rows.data.0.name', 'Banyak Isi')
            ->where('rows.data.0.competencies_count', 2)
            ->where('rows.data.0.verified_count', 1)
            ->where('rows.data.1.competencies_count', 0));

    $this->get(route('admin.competency-directory.index', ['view' => 'member', 'filled' => 'empty']))
        ->assertInertia(fn (Assert $page) => $page->has('rows.data', 1)->where('rows.data.0.name', 'Belum Isi'));
    $this->get(route('admin.competency-directory.index', ['view' => 'member', 'filled' => 'filled']))
        ->assertInertia(fn (Assert $page) => $page->has('rows.data', 1)->where('rows.data.0.name', 'Banyak Isi'));
});

test('by member can find people holding a competency at a minimum level', function () {
    $analysis = skill('C001', 'Analisis Data');
    holds(person('Jago'), $analysis, 4);
    holds(person('Pemula'), $analysis, 1);
    person('Tanpa Data');

    $this->actingAs(directoryViewer())
        ->get(route('admin.competency-directory.index', ['view' => 'member', 'competency' => (string) $analysis->id, 'min_level' => 3]))
        ->assertInertia(fn (Assert $page) => $page->has('rows.data', 1)->where('rows.data.0.name', 'Jago'));
});

test('administrators who are not QCC participants are not listed as people without competencies', function () {
    $admin = User::factory()->create(['name' => 'Cuma Admin']);
    $admin->assignRole(Role::findOrCreate('Super Admin', 'web'));
    $both = person('Admin Sekaligus Leader');
    $both->assignRole('Super Admin');

    $this->actingAs(directoryViewer())
        ->get(route('admin.competency-directory.index', ['view' => 'member']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('rows.data', fn ($rows) => collect($rows)->pluck('name')->all() === ['Admin Sekaligus Leader']));
});

test('a member profile lists every competency of that person', function () {
    $person = person('Budi Santoso');
    holds($person, skill('C002', 'Negosiasi'), 2);
    holds($person, skill('C001', 'Analisis Data'), 4, verified: 3);

    $this->actingAs(directoryViewer())
        ->get(route('admin.competency-directory.index', ['member' => $person->id]))
        ->assertInertia(fn (Assert $page) => $page
            ->where('selectedMember.name', 'Budi Santoso')
            ->has('selectedMember.competencies', 2)
            ->where('selectedMember.competencies.0.code', 'C001')
            ->where('selectedMember.competencies.0.verified_level_label', 'Proficient')
            ->where('selectedMember.competencies.1.verified_level', null));
});

test('only work data is exposed, never personal contacts or secrets', function () {
    $person = person('Budi Santoso');
    $person->forceFill(['work_contact' => '0811111', 'personal_contact' => '0822222'])->save();
    holds($person, skill('C001', 'Analisis Data'), 3);

    $viewer = directoryViewer();
    $byCompetency = $this->actingAs($viewer)->get(route('admin.competency-directory.index'))->getContent();
    $byMember = $this->get(route('admin.competency-directory.index', ['view' => 'member', 'member' => $person->id]))->getContent();

    foreach ([$byCompetency, $byMember] as $html) {
        expect($html)->not->toContain('0811111')
            ->and($html)->not->toContain('0822222')
            ->and($html)->not->toContain('password');
    }
});

test('a page of results is loaded without a query per row', function () {
    $analysis = skill('C001', 'Analisis Data');
    $org = Organization::factory()->create();

    foreach (range(1, 10) as $number) {
        holds(person("Orang {$number}", $org), $analysis, 3);
    }

    $viewer = directoryViewer();
    $this->actingAs($viewer);

    $queries = 0;
    DB::listen(function () use (&$queries) {
        $queries++;
    });

    $this->get(route('admin.competency-directory.index'))->assertOk();

    expect($queries)->toBeLessThan(25);
});

test('unknown filters and sort columns are rejected', function () {
    $this->actingAs(directoryViewer());

    $this->get(route('admin.competency-directory.index', ['sort' => 'password']))->assertSessionHasErrors('sort');
    $this->get(route('admin.competency-directory.index', ['view' => 'hacker']))->assertSessionHasErrors('view');
    $this->get(route('admin.competency-directory.index', ['organization' => '1; DROP']))->assertSessionHasErrors('organization');
});
