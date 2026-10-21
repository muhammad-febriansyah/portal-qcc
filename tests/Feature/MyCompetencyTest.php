<?php

use App\Models\AuditLog;
use App\Models\Competency;
use App\Models\CompetencyGroup;
use App\Models\MemberCompetency;
use App\Models\User;
use App\Services\AuditDescriber;
use Inertia\Testing\AssertableInertia as Assert;

function competencyInGroup(string $code, string $name, ?CompetencyGroup $group = null, bool $active = true): Competency
{
    return Competency::factory()->create([
        'group_id' => ($group ?? CompetencyGroup::factory()->create())->id,
        'code' => $code,
        'name' => $name,
        'is_active' => $active,
    ]);
}

test('guests are redirected to login', function () {
    $this->get(route('my-competencies.index'))->assertRedirect(route('login'));
});

test('a person only sees their own competencies', function () {
    $me = User::factory()->create();
    $other = User::factory()->create();
    $mine = competencyInGroup('C001', 'Analisis Data');
    $theirs = competencyInGroup('C002', 'Negosiasi');
    MemberCompetency::factory()->create(['user_id' => $me->id, 'competency_id' => $mine->id, 'self_level' => 3]);
    MemberCompetency::factory()->create(['user_id' => $other->id, 'competency_id' => $theirs->id, 'self_level' => 4]);

    $this->actingAs($me)
        ->get(route('my-competencies.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('my-competencies/index')
            ->has('competencies.data', 1)
            ->where('competencies.data.0.code', 'C001')
            ->where('competencies.data.0.self_level_label', 'Proficient')
            ->where('competencies.data.0.verified_level', null));
});

test('the list shows the verified level apart from the self assessed one', function () {
    $me = User::factory()->create();
    MemberCompetency::factory()->verified(2)->create([
        'user_id' => $me->id,
        'competency_id' => competencyInGroup('C001', 'Analisis')->id,
        'self_level' => 4,
    ]);

    $this->actingAs($me)
        ->get(route('my-competencies.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('competencies.data.0.self_level', 4)
            ->where('competencies.data.0.verified_level', 2)
            ->where('competencies.data.0.verified_level_label', 'Basic'));
});

test('the add dialog only offers active competencies the person does not have yet', function () {
    $me = User::factory()->create();
    $taken = competencyInGroup('C001', 'Sudah Ada');
    competencyInGroup('C002', 'Masih Bisa');
    competencyInGroup('C003', 'Nonaktif', active: false);
    MemberCompetency::factory()->create(['user_id' => $me->id, 'competency_id' => $taken->id]);

    $this->actingAs($me)
        ->get(route('my-competencies.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('competencyOptions', 1)
            ->where('competencyOptions.0.label', 'C002 - Masih Bisa')
            ->has('levelOptions', 4));
});

test('the list can be searched and filtered by group', function () {
    $me = User::factory()->create();
    $groupA = CompetencyGroup::factory()->create();
    $groupB = CompetencyGroup::factory()->create();
    foreach ([['C001', 'Analisis Data', $groupA], ['C002', 'Negosiasi', $groupB]] as [$code, $name, $group]) {
        MemberCompetency::factory()->create(['user_id' => $me->id, 'competency_id' => competencyInGroup($code, $name, $group)->id]);
    }

    $this->actingAs($me);

    $this->get(route('my-competencies.index', ['search' => 'nego']))
        ->assertInertia(fn (Assert $page) => $page->has('competencies.data', 1)->where('competencies.data.0.code', 'C002'));
    $this->get(route('my-competencies.index', ['group' => (string) $groupA->id]))
        ->assertInertia(fn (Assert $page) => $page->has('competencies.data', 1)->where('competencies.data.0.code', 'C001'));
    $this->get(route('my-competencies.index', ['sort' => 'verified_level']))->assertSessionHasErrors('sort');
});

test('a person can add a competency with a level from 1 to 4', function () {
    $me = User::factory()->create();
    $competency = competencyInGroup('C001', 'Analisis Data');

    $this->actingAs($me)
        ->post(route('my-competencies.store'), ['competency_id' => $competency->id, 'self_level' => 3])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $row = MemberCompetency::query()->where('user_id', $me->id)->firstOrFail();

    expect($row->self_level)->toBe(3)
        ->and($row->verified_level)->toBeNull();
});

test('adding validates the competency and the level', function () {
    $me = User::factory()->create();
    $active = competencyInGroup('C001', 'Aktif');
    $inactive = competencyInGroup('C002', 'Nonaktif', active: false);
    MemberCompetency::factory()->create(['user_id' => $me->id, 'competency_id' => $active->id]);

    $this->actingAs($me);

    $this->post(route('my-competencies.store'), [])->assertSessionHasErrors(['competency_id', 'self_level']);
    $this->post(route('my-competencies.store'), ['competency_id' => $active->id, 'self_level' => 2])
        ->assertSessionHasErrors(['competency_id' => 'Kompetensi ini sudah ada di daftar Anda.']);
    $this->post(route('my-competencies.store'), ['competency_id' => $inactive->id, 'self_level' => 2])
        ->assertSessionHasErrors('competency_id');
    $this->post(route('my-competencies.store'), ['competency_id' => 999999, 'self_level' => 2])
        ->assertSessionHasErrors('competency_id');

    $fresh = competencyInGroup('C003', 'Baru');
    foreach ([0, 5, 'x'] as $badLevel) {
        $this->post(route('my-competencies.store'), ['competency_id' => $fresh->id, 'self_level' => $badLevel])
            ->assertSessionHasErrors('self_level');
    }
});

test('two people can hold the same competency', function () {
    $competency = competencyInGroup('C001', 'Analisis Data');

    foreach ([User::factory()->create(), User::factory()->create()] as $user) {
        $this->actingAs($user)->post(route('my-competencies.store'), ['competency_id' => $competency->id, 'self_level' => 2])->assertSessionHasNoErrors();
    }

    expect(MemberCompetency::query()->count())->toBe(2);
});

test('changing the level never touches the verified level, and the competency cannot be swapped', function () {
    $me = User::factory()->create();
    $other = competencyInGroup('C009', 'Lain');
    $row = MemberCompetency::factory()->verified(2)->create([
        'user_id' => $me->id,
        'competency_id' => competencyInGroup('C001', 'Analisis')->id,
        'self_level' => 1,
    ]);
    $originalCompetency = $row->competency_id;

    $this->actingAs($me)
        ->put(route('my-competencies.update', $row), ['self_level' => 4, 'verified_level' => 4, 'competency_id' => $other->id])
        ->assertSessionHasNoErrors();

    $row->refresh();
    expect($row->self_level)->toBe(4)
        ->and($row->verified_level)->toBe(2)
        ->and($row->verification_status)->toBe('verified')
        ->and($row->competency_id)->toBe($originalCompetency);
});

test('nobody can change or delete someone else\'s competency', function () {
    $owner = User::factory()->create();
    $row = MemberCompetency::factory()->create(['user_id' => $owner->id, 'self_level' => 2]);

    $this->actingAs(User::factory()->create());

    $this->put(route('my-competencies.update', $row), ['self_level' => 4])->assertForbidden();
    $this->delete(route('my-competencies.destroy', $row))->assertForbidden();

    expect($row->fresh()->self_level)->toBe(2);
});

test('an unverified competency can be removed but a verified one cannot', function () {
    $me = User::factory()->create();
    $loose = MemberCompetency::factory()->create(['user_id' => $me->id]);
    $verified = MemberCompetency::factory()->verified()->create(['user_id' => $me->id]);

    $this->actingAs($me);
    $this->delete(route('my-competencies.destroy', $loose))->assertRedirect();
    $this->delete(route('my-competencies.destroy', $verified))->assertRedirect();

    expect(MemberCompetency::query()->whereKey($loose->id)->exists())->toBeFalse()
        ->and(MemberCompetency::query()->whereKey($verified->id)->exists())->toBeTrue();
});

test('changes are audited in plain language', function () {
    $me = User::factory()->create(['name' => 'Rina Peserta']);
    $competency = competencyInGroup('C001', 'Analisis Data');

    $this->actingAs($me);
    $this->post(route('my-competencies.store'), ['competency_id' => $competency->id, 'self_level' => 2]);
    $row = MemberCompetency::query()->firstOrFail();
    $this->put(route('my-competencies.update', $row), ['self_level' => 4]);

    $created = AuditLog::query()->where('event', 'member_competency.created')->with('actor')->firstOrFail();
    $updated = AuditLog::query()->where('event', 'member_competency.updated')->with('actor')->firstOrFail();
    $describer = new AuditDescriber;

    expect($describer->describe($created))->toBe('Rina Peserta menambahkan kompetensi C001 - Analisis Data ke daftarnya')
        ->and($describer->describe($updated))->toBe('Rina Peserta mengubah level kompetensi C001 - Analisis Data (level saya)')
        ->and($describer->changes($updated))->toBe([['label' => 'Level saya', 'before' => 'Basic', 'after' => 'Expert']]);
});
