<?php

use App\Models\Competency;
use App\Models\CompetencyGroup;
use App\Models\MemberCompetency;
use Illuminate\Support\Facades\DB;

test('the migration loads the skill dictionary and switches the old competencies off without deleting them', function () {
    $old = Competency::factory()->create(['code' => 'C001', 'group_id' => CompetencyGroup::factory()->create()->id]);
    $held = MemberCompetency::factory()->create(['competency_id' => $old->id]);

    $migration = require database_path('migrations/2026_11_09_162122_replace_competency_master_with_skill_dictionary.php');
    $migration->up();

    $dictionary = Competency::query()->where('is_active', true)->orderBy('code')->get();

    expect($dictionary)->toHaveCount(66)
        ->and($dictionary->first()->code)->toBe('H01')
        ->and($dictionary->first()->description)->toStartWith('Memahami cara kerja bisnis outsourcing')
        ->and($dictionary->whereNotNull('group_id'))->toBeEmpty()
        ->and($dictionary->last()->code)->toBe('S18')
        ->and($old->fresh()->is_active)->toBeFalse()
        ->and(MemberCompetency::query()->whereKey($held->id)->exists())->toBeTrue();
});

test('a fresh installation without competencies is left to the seeder', function () {
    $migration = require database_path('migrations/2026_11_09_162122_replace_competency_master_with_skill_dictionary.php');
    $migration->up();

    expect(DB::table('competency_master')->count())->toBe(0);
});
