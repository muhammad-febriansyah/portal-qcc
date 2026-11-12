<?php

use App\Models\Organization;
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

test('it stores the SIM ID number and gives "SIM" plus that number as first password', function () {
    $path = userWorkbook([
        [1493, 'GILANG RAMADHAN', " GILANG.RAMADHAN@SIM.CO.ID\u{00A0}", 'SIM1493', 'SIMers'],
        [1000, 'I DEWA PUTU PRAYOGA', 'idp.prayoga@sim.co.id', 'SIM1000', 'Mentor/Reviewer'],
        [2685, 'MOHAMMAD ALI RIDНА', 'm.aliridha@sim.co.id', 'SIM2685', 'QCC Owner'],
    ]);

    $this->artisan('qcc:users:import', ['path' => $path])->assertSuccessful();

    $member = User::query()->where('sim_id', '1493')->firstOrFail();
    expect($member->email)->toBe('gilang.ramadhan@sim.co.id')
        ->and($member->hasRole('SIMer / Member'))->toBeTrue()
        ->and(Hash::check('SIM1493', $member->password))->toBeTrue()
        ->and($member->needs_profile_setup)->toBeTrue()
        ->and($member->email_verified_at)->not->toBeNull();

    expect(User::query()->where('sim_id', '1000')->firstOrFail()->hasRole('Mentor'))->toBeTrue();

    $owner = User::query()->where('sim_id', '2685')->firstOrFail();
    expect($owner->name)->toBe('MOHAMMAD ALI RIDHA')
        ->and($owner->hasRole('Owner QCC'))->toBeTrue();
});

test('running it again updates existing accounts without resetting passwords', function () {
    $existing = User::factory()->create(['sim_id' => '1493', 'email' => 'old@sim.co.id', 'password' => 'changed-password']);
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

/**
 * @param  list<list<string|int|null>>  $rows
 */
function userWorkbookWithOrganization(array $rows): string
{
    $path = tempnam(sys_get_temp_dir(), 'users-import-').'.xlsx';
    (new XlsxWriter)->write($path, [[
        'name' => 'Sheet1',
        'rows' => [['SIM ID', 'EMPLOYEE NAME', 'LOB/DIV', 'E-MAIL KANTOR', 'PASSWORD', 'POSITION'], ...$rows],
    ]]);

    return $path;
}

test('it places members in the portal organization named by their LOB or DIV', function () {
    $srnm = Organization::factory()->create(['name' => 'DIV SRNM']);
    $hcgs = Organization::factory()->create(['name' => 'DIV HCGS CORDEV']);
    $labour = Organization::factory()->create(['name' => 'LOB MSM LO']);
    $cccm = Organization::factory()->create(['name' => 'LOB CCCM']);

    $this->artisan('qcc:users:import', ['path' => userWorkbookWithOrganization([
        [1001, 'SATU', 'SRNM', 'satu@sim.co.id', 'SIM1001', 'SIMers'],
        [1002, 'DUA', 'HCGS & CORDEV', 'dua@sim.co.id', 'SIM1002', 'SIMers'],
        [1003, 'TIGA', 'LOB MSM LABOUR', 'tiga@sim.co.id', 'SIM1003', 'SIMers'],
        [1004, 'EMPAT', 'LOB CCCM', 'empat@sim.co.id', 'SIM1004', 'QCC Owner'],
    ])])->assertSuccessful();

    expect(User::query()->orderBy('sim_id')->pluck('organization_id')->all())
        ->toBe([$srnm->id, $hcgs->id, $labour->id, $cccm->id]);
});

test('an unknown LOB or DIV stops the import unless new organizations may be created', function () {
    $rows = [[1001, 'SATU', 'BOD', 'satu@sim.co.id', 'SIM1001', 'SIMers']];

    $this->artisan('qcc:users:import', ['path' => userWorkbookWithOrganization($rows)])->assertFailed();
    expect(User::query()->count())->toBe(0)
        ->and(Organization::query()->count())->toBe(0);

    $this->artisan('qcc:users:import', ['path' => userWorkbookWithOrganization($rows), '--create-organizations' => true])->assertSuccessful();

    $organization = Organization::query()->sole();
    expect($organization->name)->toBe('BOD')
        ->and($organization->is_active)->toBeTrue()
        ->and(User::query()->sole()->organization_id)->toBe($organization->id);
});

/**
 * A workbook shaped like the HR file: a "Mentor" sheet whose MENTOR cell is filled on the first
 * row of each LOB, and a "User-Role" sheet listing everyone, including the mentors.
 *
 * @param  list<list<string|int|null>>  $mentorRows
 * @param  list<list<string|int|null>>  $userRows
 */
function mentorWorkbook(array $mentorRows, array $userRows): string
{
    $path = tempnam(sys_get_temp_dir(), 'users-import-').'.xlsx';
    (new XlsxWriter)->write($path, [
        [
            'name' => 'Mentor',
            'rows' => [['SIM ID', 'EMPLOYEE NAME', 'LOB/DIV', 'MENTOR', 'E-MAIL KANTOR', 'PASSWORD', 'POSITION'], ...$mentorRows],
        ],
        [
            'name' => 'User-Role',
            'rows' => [['SIM ID', 'EMPLOYEE NAME', 'LOB/DIV', 'E-MAIL KANTOR', 'PASSWORD', 'POSITION'], ...$userRows],
        ],
    ]);

    return $path;
}

test('it reads every sheet once and gives each LOB the mentors named in the MENTOR column', function () {
    $cccm = Organization::factory()->create(['name' => 'LOB CCCM']);
    $finance = Organization::factory()->create(['name' => 'DIV FINANCE']);
    $oldMentor = User::factory()->create(['sim_id' => '9999', 'email' => 'lama@sim.co.id']);
    $cccm->mentors()->attach($oldMentor);

    $path = mentorWorkbook(
        [
            [2259, 'ANISSA MAULINA', 'LOB CCCM', 'DODDI WALYADI/MOH. BADROS SOLEH', 'anissa.maulina@sim.co.id', 'SIM2259', 'SIMers'],
            [1602, 'MINDA FITDIANA', 'LOB CCCM', 'MOH. BADROS SOLEH / DODDI WALYADI', 'minda.fitdiana@sim.co.id', 'SIM1602', 'SIMers'],
            [1340, 'EVIN KHALSA SITEPU', 'LOB CCCM', null, 'evin.khalsa@sim.co.id', 'SIM1340', 'SIMers'],
            [1091, 'RONA RINELFA', 'FINANCE', 'FEBRIAN PRATAMA', 'rona.rinelfa@sim.co.id', 'SIM1091', 'SIMers'],
        ],
        [
            [2167, 'DODDI WALYADI', 'LOB CCCM', 'doddi.walyadi@sim.co.id', 'SIM2167', 'Mentor/Reviewer'],
            [1929, 'MOH. BADROS SOLEH', 'LOB CCCM', 'BADROS.SOLEH@SIM.CO.ID', 'SIM1929', 'Mentor/Reviewer'],
            [1031, 'FEBRIAN PRATAMA', 'FINANCE', 'febrian.pratama@sim.co.id', 'SIM1031', 'Mentor/Reviewer'],
            [2259, 'ANISSA MAULINA', 'LOB CCCM', 'anissa.maulina@sim.co.id', 'SIM2259', 'SIMers'],
            [1602, 'MINDA FITDIANA', 'LOB CCCM', 'minda.fitdiana@sim.co.id', 'SIM1602', 'SIMers'],
            [1340, 'EVIN KHALSA SITEPU', 'LOB CCCM', 'evin.khalsa@sim.co.id', 'SIM1340', 'SIMers'],
            [1091, 'RONA RINELFA', 'FINANCE', 'rona.rinelfa@sim.co.id', 'SIM1091', 'SIMers'],
        ],
    );

    $this->artisan('qcc:users:import', ['path' => $path])->assertSuccessful();

    expect(User::query()->where('email', '!=', 'lama@sim.co.id')->count())->toBe(7)
        ->and($cccm->mentors()->orderBy('sim_id')->pluck('sim_id')->all())->toBe(['1929', '2167'])
        ->and($finance->mentors()->pluck('sim_id')->all())->toBe(['1031'])
        ->and(User::query()->where('sim_id', '1929')->sole()->hasRole('Mentor'))->toBeTrue();
});

test('a mentor name that is nobody in the file or the portal stops the import', function () {
    Organization::factory()->create(['name' => 'LOB CCCM']);

    $this->artisan('qcc:users:import', ['path' => mentorWorkbook(
        [[2259, 'ANISSA MAULINA', 'LOB CCCM', 'ORANG TIDAK DIKENAL', 'anissa.maulina@sim.co.id', 'SIM2259', 'SIMers']],
        [[2259, 'ANISSA MAULINA', 'LOB CCCM', 'anissa.maulina@sim.co.id', 'SIM2259', 'SIMers']],
    )])->assertFailed();

    expect(User::query()->count())->toBe(0);
});

test('the same SIM ID with a different email on another sheet stops the import', function () {
    Organization::factory()->create(['name' => 'LOB CCCM']);

    $this->artisan('qcc:users:import', ['path' => mentorWorkbook(
        [[2259, 'ANISSA MAULINA', 'LOB CCCM', null, 'anissa.maulina@sim.co.id', 'SIM2259', 'SIMers']],
        [[2259, 'ANISSA MAULINA', 'LOB CCCM', 'anissa.lain@sim.co.id', 'SIM2259', 'SIMers']],
    )])->assertFailed();

    expect(User::query()->count())->toBe(0);
});

test('accounts missing from the file are deactivated only when asked, sparing Super Admins', function () {
    $leaver = User::factory()->create(['sim_id' => '1111', 'email' => 'keluar@sim.co.id']);
    $admin = User::factory()->create(['sim_id' => '2222', 'email' => 'admin@sim.co.id']);
    $admin->assignRole(Role::findOrCreate('Super Admin', 'web'));
    $path = userWorkbook([[1493, 'GILANG RAMADHAN', 'gilang.ramadhan@sim.co.id', 'SIM1493', 'SIMers']]);

    $this->artisan('qcc:users:import', ['path' => $path])->assertSuccessful();
    expect($leaver->fresh()->is_active)->toBeTrue();

    $this->artisan('qcc:users:import', ['path' => $path, '--deactivate-missing' => true])->assertSuccessful();
    expect($leaver->fresh()->is_active)->toBeFalse()
        ->and($admin->fresh()->is_active)->toBeTrue();
});
