<?php

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('stores proposal, requirement, team and matching records with their unique constraints', function () {
    $timestamp = now();

    $batchId = DB::table('qcc_batches')->insertGetId([
        'code' => 'BATCH-TEST',
        'name' => 'Test Batch',
        'starts_on' => $timestamp->toDateString(),
        'ends_on' => $timestamp->copy()->addMonth()->toDateString(),
        'status' => 'ACTIVE',
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);
    $user = User::factory()->create();
    $groupId = DB::table('competency_groups')->insertGetId([
        'code' => 'A',
        'name' => 'Group A',
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);
    $competencyId = DB::table('competency_master')->insertGetId([
        'group_id' => $groupId,
        'code' => 'C001',
        'name' => 'Test Competency',
        'is_active' => true,
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);
    $projectId = DB::table('projects')->insertGetId([
        'batch_id' => $batchId,
        'proposer_id' => $user->id,
        'title' => 'Test project',
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);

    DB::table('project_requirements')->insert([
        'project_id' => $projectId,
        'competency_id' => $competencyId,
        'minimum_level' => 2,
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);
    DB::table('project_members')->insert([
        'project_id' => $projectId,
        'user_id' => $user->id,
        'role_in_project' => 'LEADER',
        'status' => 'DRAFT',
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);
    DB::table('team_matching_runs')->insert([
        'project_id' => $projectId,
        'requested_by' => $user->id,
        'status' => 'PENDING',
        'idempotency_key' => 'match-test-1',
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]);

    $this->assertDatabaseHas('projects', ['id' => $projectId, 'proposal_status' => 'DRAFT']);
    $this->assertDatabaseHas('project_requirements', [
        'project_id' => $projectId,
        'competency_id' => $competencyId,
    ]);
    $this->assertDatabaseHas('project_members', ['project_id' => $projectId, 'user_id' => $user->id]);
    $this->assertDatabaseHas('team_matching_runs', [
        'project_id' => $projectId,
        'idempotency_key' => 'match-test-1',
    ]);

    expect(fn () => DB::table('project_requirements')->insert([
        'project_id' => $projectId,
        'competency_id' => $competencyId,
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]))->toThrow(QueryException::class);

    expect(fn () => DB::table('project_members')->insert([
        'project_id' => $projectId,
        'user_id' => $user->id,
        'role_in_project' => 'LEADER',
        'status' => 'DRAFT',
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]))->toThrow(QueryException::class);

    expect(fn () => DB::table('team_matching_runs')->insert([
        'project_id' => $projectId,
        'requested_by' => $user->id,
        'status' => 'PENDING',
        'idempotency_key' => 'match-test-1',
        'created_at' => $timestamp,
        'updated_at' => $timestamp,
    ]))->toThrow(QueryException::class);
});
