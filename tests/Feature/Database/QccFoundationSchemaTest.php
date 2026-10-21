<?php

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

it('stores self and verified competency levels separately and prevents duplicate assignments', function () {
    $organizationId = DB::table('organizations')->insertGetId([
        'code' => 'LOB-TEST',
        'name' => 'Test Organization',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $user = User::factory()->create();
    DB::table('users')->where('id', $user->id)->update(['organization_id' => $organizationId]);

    $groupId = DB::table('competency_groups')->insertGetId([
        'code' => 'A',
        'name' => 'Group A',
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $competencyId = DB::table('competency_master')->insertGetId([
        'group_id' => $groupId,
        'code' => 'C001',
        'name' => 'Test Competency',
        'is_active' => true,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    DB::table('member_competencies')->insert([
        'user_id' => $user->id,
        'competency_id' => $competencyId,
        'self_level' => 2,
        'verified_level' => 3,
        'verified_by' => null,
        'verified_at' => null,
        'verification_status' => null,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $this->assertDatabaseHas('member_competencies', [
        'user_id' => $user->id,
        'competency_id' => $competencyId,
        'self_level' => 2,
        'verified_level' => 3,
    ]);

    expect(fn () => DB::table('member_competencies')->insert([
        'user_id' => $user->id,
        'competency_id' => $competencyId,
        'self_level' => 4,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});
