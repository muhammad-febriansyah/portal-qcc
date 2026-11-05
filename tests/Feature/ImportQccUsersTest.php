<?php

use App\Models\User;
use App\Services\Spreadsheet\XlsxWriter;
use Illuminate\Support\Facades\Hash;
use Spatie\Permission\Models\Role;

beforeEach(function () {
    foreach (['SIMer / Member', 'Mentor', 'Owner QCC'] as $role) {
        Role::findOrCreate($role, 'web');
    }
});

/**
 * @param  list<list<string|int|null>>  $rows
 */
function userWorkbook(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'users-import-').'.xlsx';
    (new XlsxWriter)->write($path, [[
        'name' => 'Sheet1',
        'rows' => [['SIM ID', 'EMPLOYEE NAME', 'E-MAIL KANTOR', 'PASSWORD', 'POSITION'], ...$rows],
    ]]);

    return $path;
}

test('it creates accounts with roles and the SIM ID as first password', function () {
    $path = userWorkbook([
        [1493, 'GILANG RAMADHAN', " GILANG.RAMADHAN@SIM.CO.ID\u{00A0}", 'SIM1493', 'SIMers'],
        [1000, 'I DEWA PUTU PRAYOGA', 'idp.prayoga@sim.co.id', 'SIM1000', 'Mentor/Reviewer'],
        [2685, 'MOHAMMAD ALI RIDНА', 'm.aliridha@sim.co.id', 'SIM2685', 'QCC Owner'],
    ]);

    $this->artisan('qcc:users:import', ['path' => $path])->assertSuccessful();

    $member = User::query()->where('sim_id', 'SIM1493')->firstOrFail();
    expect($member->email)->toBe('gilang.ramadhan@sim.co.id')
        ->and($member->hasRole('SIMer / Member'))->toBeTrue()
        ->and(Hash::check('SIM1493', $member->password))->toBeTrue()
        ->and($member->needs_profile_setup)->toBeTrue()
        ->and($member->email_verified_at)->not->toBeNull();

    expect(User::query()->where('sim_id', 'SIM1000')->firstOrFail()->hasRole('Mentor'))->toBeTrue();

    $owner = User::query()->where('sim_id', 'SIM2685')->firstOrFail();
    expect($owner->name)->toBe('MOHAMMAD ALI RIDHA')
        ->and($owner->hasRole('Owner QCC'))->toBeTrue();
});

test('running it again updates existing accounts without resetting passwords', function () {
    $existing = User::factory()->create(['sim_id' => 'SIM1493', 'email' => 'old@sim.co.id', 'password' => 'changed-password']);
    $existing->assignRole('Mentor');

    $this->artisan('qcc:users:import', ['path' => userWorkbook([
        [1493, 'GILANG RAMADHAN', 'gilang.ramadhan@sim.co.id', 'SIM1493', 'SIMers'],
    ])])->assertSuccessful();

    $existing->refresh();
    expect(User::query()->count())->toBe(1)
        ->and($existing->email)->toBe('gilang.ramadhan@sim.co.id')
        ->and($existing->getRoleNames()->all())->toBe(['SIMer / Member'])
        ->and(Hash::check('changed-password', $existing->password))->toBeTrue();
});

test('it saves nothing when any row is invalid', function () {
    $this->artisan('qcc:users:import', ['path' => userWorkbook([
        [1493, 'GILANG RAMADHAN', 'gilang.ramadhan@sim.co.id', 'SIM1493', 'SIMers'],
        [1494, 'TANPA POSISI', 'tanpa.posisi@sim.co.id', 'SIM1494', 'Direktur'],
        [1495, 'EMAIL GANDA', 'gilang.ramadhan@sim.co.id', 'SIM1495', 'SIMers'],
    ])])->assertFailed();

    expect(User::query()->count())->toBe(0);
});

test('dry run reports without saving', function () {
    $this->artisan('qcc:users:import', [
        'path' => userWorkbook([[1493, 'GILANG RAMADHAN', 'gilang.ramadhan@sim.co.id', 'SIM1493', 'SIMers']]),
        '--dry-run' => true,
    ])->assertSuccessful();

    expect(User::query()->count())->toBe(0);
});
