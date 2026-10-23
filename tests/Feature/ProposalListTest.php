<?php

use App\Enums\ProposalStatus;
use App\Models\Organization;
use App\Models\Project;
use App\Models\QccBatch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

function proposalViewer(string $role = 'Owner QCC', bool $viewAny = true): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::findOrCreate($role, 'web'));
    $user->givePermissionTo(Permission::findOrCreate('projects.view', 'web'));

    if ($viewAny) {
        $user->givePermissionTo(Permission::findOrCreate('projects.viewAny', 'web'));
    }

    return $user;
}

function joinProject(Project $project, User $user, string $role = 'MEMBER'): void
{
    DB::table('project_members')->insert([
        'project_id' => $project->id,
        'user_id' => $user->id,
        'role_in_project' => $role,
        'status' => 'ACTIVE',
        'joined_at' => now(),
        'created_at' => now(),
        'updated_at' => now(),
    ]);
}

function proposalTitles($response): array
{
    return collect($response->viewData('page')['props']['proposals']['data'])->pluck('title')->all();
}

test('guests are sent to login', function () {
    $this->get(route('proposals.index'))->assertRedirect(route('login'));
});

test('users without project access are forbidden', function () {
    $this->actingAs(User::factory()->create())->get(route('proposals.index'))->assertForbidden();
});

test('an owner sees every proposal with batch, proposer and member count', function () {
    $organization = Organization::factory()->create(['name' => 'LOB Retail']);
    $proposer = User::factory()->create(['name' => 'Budi Pengaju', 'organization_id' => $organization->id]);
    $batch = QccBatch::factory()->create(['name' => 'Batch Pertama']);
    $project = Project::factory()->create(['title' => 'Kurangi antrean', 'batch_id' => $batch->id, 'proposer_id' => $proposer->id]);
    joinProject($project, $proposer, 'LEADER');
    Project::factory()->create(['title' => 'Proposal lain']);

    $this->actingAs(proposalViewer())
        ->get(route('proposals.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->component('proposals/index')
            ->has('proposals.data', 2)
            ->where('proposals.data', fn ($rows) => collect($rows)->contains(fn ($row) => $row['title'] === 'Kurangi antrean'
                && $row['code'] === sprintf('PRP-%04d', $project->id)
                && $row['batch'] === 'Batch Pertama'
                && $row['proposer'] === 'Budi Pengaju'
                && $row['organization'] === 'LOB Retail'
                && $row['members_count'] === 1
                && $row['status'] === 'DRAFT'))
            ->has('statusOptions', 6));
});

test('a member only sees proposals they proposed or belong to', function () {
    $member = proposalViewer('SIMer / Member', viewAny: false);
    $own = Project::factory()->create(['title' => 'Milik sendiri', 'proposer_id' => $member->id]);
    $joined = Project::factory()->create(['title' => 'Tim saya']);
    joinProject($joined, $member);
    Project::factory()->create(['title' => 'Punya orang lain']);

    $response = $this->actingAs($member)->get(route('proposals.index'));

    expect(proposalTitles($response))->toEqualCanonicalizing(['Milik sendiri', 'Tim saya']);
});

test('search matches title, proposer name and code', function () {
    $viewer = proposalViewer();
    $target = Project::factory()->create(['title' => 'Percepat klaim', 'proposer_id' => User::factory()->create(['name' => 'Sari Unik'])->id]);
    Project::factory()->create(['title' => 'Hal lain']);

    expect(proposalTitles($this->actingAs($viewer)->get(route('proposals.index', ['search' => 'klaim']))))->toBe(['Percepat klaim'])
        ->and(proposalTitles($this->actingAs($viewer)->get(route('proposals.index', ['search' => 'Sari Unik']))))->toBe(['Percepat klaim'])
        ->and(proposalTitles($this->actingAs($viewer)->get(route('proposals.index', ['search' => sprintf('PRP-%04d', $target->id)]))))->toBe(['Percepat klaim']);
});

test('status and batch filters narrow the list', function () {
    $viewer = proposalViewer();
    $batch = QccBatch::factory()->create();
    Project::factory()->status(ProposalStatus::Approved)->create(['title' => 'Disetujui', 'batch_id' => $batch->id]);
    Project::factory()->status(ProposalStatus::Draft)->create(['title' => 'Draf', 'batch_id' => $batch->id]);
    Project::factory()->status(ProposalStatus::Approved)->create(['title' => 'Batch lain']);

    expect(proposalTitles($this->actingAs($viewer)->get(route('proposals.index', ['status' => 'APPROVED', 'batch' => $batch->id]))))->toBe(['Disetujui']);
});

test('invalid filter values are rejected', function () {
    $this->actingAs(proposalViewer())->get(route('proposals.index', ['status' => 'NOPE']))->assertSessionHasErrors('status');
});

test('the list paginates in SQL and does not run a query per row', function () {
    $viewer = proposalViewer();
    Project::factory()->create();

    $queryCount = 0;
    DB::listen(function () use (&$queryCount) {
        $queryCount++;
    });

    $this->actingAs($viewer)->get(route('proposals.index', ['per_page' => 10]))
        ->assertInertia(fn (Assert $page) => $page->has('proposals.data', 1)->where('proposals.total', 1));
    $singleRowQueries = $queryCount;

    Project::factory()->count(11)->create();
    $manyRowsStart = $queryCount;

    $this->actingAs($viewer)->get(route('proposals.index', ['per_page' => 10]))
        ->assertInertia(fn (Assert $page) => $page->has('proposals.data', 10)->where('proposals.total', 12));

    expect($queryCount - $manyRowsStart)->toBeLessThanOrEqual($singleRowQueries + 1);
});

test('proposal sorting is applied by the server', function () {
    $viewer = proposalViewer();
    Project::factory()->create(['title' => 'Zebra']);
    Project::factory()->create(['title' => 'Alpha']);

    expect(proposalTitles($this->actingAs($viewer)->get(route('proposals.index', [
        'sort' => 'title',
        'direction' => 'asc',
    ]))))->toBe(['Alpha', 'Zebra']);
});

test('a proposer can view their submitted proposal details', function () {
    $proposer = proposalViewer('Team Leader', viewAny: false);
    $project = Project::factory()->status(ProposalStatus::Submitted)->create([
        'proposer_id' => $proposer->id,
        'background' => 'Background details',
        'learning_goals_json' => ['Improve problem analysis'],
    ]);

    $this->actingAs($proposer)
        ->get(route('proposals.show', $project))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('proposals/show')
            ->where('proposal.id', $project->id)
            ->where('proposal.code', sprintf('PRP-%04d', $project->id))
            ->where('proposal.status_label', 'Diajukan')
            ->where('proposal.background', 'Background details')
            ->where('proposal.learning_goals.0', 'Improve problem analysis')
            ->where('proposal.can_edit_draft', false));
});

test('an unrelated member cannot view proposal details directly', function () {
    $member = proposalViewer('SIMer / Member', viewAny: false);
    $project = Project::factory()->status(ProposalStatus::Submitted)->create();

    $this->actingAs($member)->get(route('proposals.show', $project))->assertForbidden();
});

test('the index carries both the proposal table and my own drafts', function () {
    $owner = proposalViewer('Team Leader', viewAny: false);
    $owner->givePermissionTo(Permission::findOrCreate('projects.create', 'web'));
    $draft = Project::factory()->create(['title' => 'Draf saya', 'proposer_id' => $owner->id]);
    Project::factory()->status(ProposalStatus::Draft)->create(['title' => 'Draf orang lain']);

    $this->actingAs($owner)->get(route('proposals.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('drafts', 1)
            ->where('drafts.0.id', $draft->id)
            ->where('can_create', true)
            ->has('proposals.data', 1)
            ->where('proposals.data.0.can_edit_draft', true)
            ->where('proposals.data.0.can_find_candidates', true));
});
