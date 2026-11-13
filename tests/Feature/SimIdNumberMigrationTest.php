<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('the migration keeps only the number of SIM IDs and leaves passwords alone', function () {
    $naomi = User::factory()->create(['sim_id' => 'SIM2551', 'password' => 'SIM2551']);
    $dashed = User::factory()->create(['sim_id' => 'SIM-0042']);
    $numberOnly = User::factory()->create(['sim_id' => '777']);
    $custom = User::factory()->create(['sim_id' => 'EXT-9']);
    $taken = User::factory()->create(['sim_id' => '300']);
    $clash = User::factory()->create(['sim_id' => 'SIM300']);

    $migration = require database_path('migrations/2026_11_09_100957_strip_sim_prefix_from_user_sim_ids.php');
    $migration->up();

    expect($naomi->fresh()->sim_id)->toBe('2551')
        ->and(Hash::check('SIM2551', $naomi->fresh()->password))->toBeTrue()
        ->and($dashed->fresh()->sim_id)->toBe('0042')
        ->and($numberOnly->fresh()->sim_id)->toBe('777')
        ->and($custom->fresh()->sim_id)->toBe('EXT-9')
        ->and($taken->fresh()->sim_id)->toBe('300')
        ->and($clash->fresh()->sim_id)->toBe('SIM300');
});
