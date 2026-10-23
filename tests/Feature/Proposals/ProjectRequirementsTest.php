<?php

use App\Enums\ProposalStatus;
use App\Models\Competency;
use App\Models\Project;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function requirementsEditor(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate('SIMer / Member', 'web'));
    $user->givePermissionTo([
        Permission::findOrCreate('projects.view', 'web'),
        Permission::findOrCreate('projects.update', 'web'),
    ]);

    return $user;
}

function savedRequirements(Project $project): array
{
    return DB::table('project_requirements')->where('project_id', $project->id)->orderBy('competency_id')
        ->get(['competency_id', 'minimum_level'])
        ->map(fn ($row) => [(int) $row->competency_id, $row->minimum_level === null ? null : (int) $row->minimum_level])
        ->all();
}

beforeEach(function () {
    $this->editor = requirementsEditor();
    $this->project = Project::factory()->create(['proposer_id' => $this->editor->id]);
    $this->a = Competency::factory()->create(['is_active' => true]);
    $this->b = Competency::factory()->create(['is_active' => true]);
});

it('saves competencies from the master and the level stays optional', function () {
    $this->actingAs($this->editor)
        ->put(route('proposals.requirements.update', $this->project), ['requirements' => [
            ['competency_id' => $this->a->id, 'minimum_level' => 3],
            ['competency_id' => $this->b->id, 'minimum_level' => null],
        ]])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    expect(savedRequirements($this->project))->toBe([[$this->a->id, 3], [$this->b->id, null]]);
});

it('refuses the same competency twice and saves nothing', function () {
    $this->actingAs($this->editor)
        ->put(route('proposals.requirements.update', $this->project), ['requirements' => [
            ['competency_id' => $this->a->id, 'minimum_level' => 2],
            ['competency_id' => $this->a->id, 'minimum_level' => 4],
        ]])
        ->assertSessionHasErrors(['requirements.0.competency_id', 'requirements.1.competency_id']);

    expect(savedRequirements($this->project))->toBe([]);
});

it('only accepts active competencies that exist in the master', function () {
    $inactive = Competency::factory()->create(['is_active' => false]);

    $this->actingAs($this->editor)
        ->put(route('proposals.requirements.update', $this->project), ['requirements' => [
            ['competency_id' => $inactive->id],
            ['competency_id' => 999999],
        ]])
        ->assertSessionHasErrors(['requirements.0.competency_id', 'requirements.1.competency_id']);

    expect(savedRequirements($this->project))->toBe([]);
});

it('keeps the level between 1 and 4 when one is given', function () {
    foreach ([0, 5, 'tinggi'] as $level) {
        $this->actingAs($this->editor)
            ->put(route('proposals.requirements.update', $this->project), ['requirements' => [
                ['competency_id' => $this->a->id, 'minimum_level' => $level],
            ]])
            ->assertSessionHasErrors('requirements.0.minimum_level');
    }
});

it('replaces the list: kept rows stay, removed rows go, a level can be cleared', function () {
    $c = Competency::factory()->create(['is_active' => true]);
    $url = route('proposals.requirements.update', $this->project);

    $this->actingAs($this->editor)->put($url, ['requirements' => [
        ['competency_id' => $this->a->id, 'minimum_level' => 3],
        ['competency_id' => $this->b->id, 'minimum_level' => 2],
    ]]);

    DB::table('project_requirements')->where('competency_id', $this->a->id)->update(['notes' => 'catatan lama']);

    $this->actingAs($this->editor)->put($url, ['requirements' => [
        ['competency_id' => $this->a->id, 'minimum_level' => null],
        ['competency_id' => $c->id, 'minimum_level' => 1],
    ]])->assertSessionHasNoErrors();

    expect(savedRequirements($this->project))->toBe([[$this->a->id, null], [$c->id, 1]])
        ->and(DB::table('project_requirements')->where('competency_id', $this->a->id)->value('notes'))->toBe('catatan lama');

    $this->actingAs($this->editor)->put($url, ['requirements' => []])->assertSessionHasNoErrors();

    expect(savedRequirements($this->project))->toBe([]);
});

it('limits the list size', function () {
    $many = Competency::factory()->count(21)->create(['is_active' => true])
        ->map(fn ($competency) => ['competency_id' => $competency->id])->all();

    $this->actingAs($this->editor)
        ->put(route('proposals.requirements.update', $this->project), ['requirements' => $many])
        ->assertSessionHasErrors('requirements');
});

it('is closed to other people and to proposals that are no longer drafts', function () {
    $payload = ['requirements' => [['competency_id' => $this->a->id]]];

    $stranger = requirementsEditor();
    $this->actingAs($stranger)->put(route('proposals.requirements.update', $this->project), $payload)->assertForbidden();

    $submitted = Project::factory()->status(ProposalStatus::Submitted)->create(['proposer_id' => $this->editor->id]);
    $this->actingAs($this->editor)->put(route('proposals.requirements.update', $submitted), $payload)->assertForbidden();

    expect(savedRequirements($this->project))->toBe([]);
});

it('hands the wizard the master list, the saved rows and the level names', function () {
    $this->actingAs($this->editor)->put(route('proposals.requirements.update', $this->project), ['requirements' => [
        ['competency_id' => $this->a->id, 'minimum_level' => 2],
    ]]);

    $this->actingAs($this->editor)->get(route('proposals.edit', $this->project))
        ->assertInertia(fn (Assert $page) => $page
            ->component('proposals/wizard')
            ->has('competencyOptions', 2)
            ->where('requirements.0.competency_id', $this->a->id)
            ->where('requirements.0.minimum_level', 2)
            ->has('levelOptions', 4));
});
