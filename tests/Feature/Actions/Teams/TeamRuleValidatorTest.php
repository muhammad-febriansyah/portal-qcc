<?php

use App\Actions\Teams\TeamRuleValidator;
use Illuminate\Validation\ValidationException;

/**
 * @param  array<int, array{user_id: int, organization_id: int|null, role_in_project: string}>  $members
 * @return array<int, string>
 */
function qcc054TeamValidationErrors(array $members): array
{
    try {
        (new TeamRuleValidator)->validate($members);
    } catch (ValidationException $exception) {
        return $exception->errors()['members'];
    }

    throw new RuntimeException('Expected team validation to fail.');
}

it('accepts a five-member team with one leader and members from two LOBs', function () {
    $members = [
        ['user_id' => 1, 'organization_id' => 10, 'role_in_project' => 'LEADER'],
        ['user_id' => 2, 'organization_id' => 20, 'role_in_project' => 'MEMBER'],
        ['user_id' => 3, 'organization_id' => 10, 'role_in_project' => 'MEMBER'],
        ['user_id' => 4, 'organization_id' => 20, 'role_in_project' => 'MEMBER'],
        ['user_id' => 5, 'organization_id' => 10, 'role_in_project' => 'MEMBER'],
    ];

    expect(fn () => (new TeamRuleValidator)->validate($members))
        ->not->toThrow(ValidationException::class);
});

it('rejects teams with more than five members', function () {
    $members = [
        ['user_id' => 1, 'organization_id' => 10, 'role_in_project' => 'LEADER'],
        ['user_id' => 2, 'organization_id' => 20, 'role_in_project' => 'MEMBER'],
        ['user_id' => 3, 'organization_id' => 10, 'role_in_project' => 'MEMBER'],
        ['user_id' => 4, 'organization_id' => 20, 'role_in_project' => 'MEMBER'],
        ['user_id' => 5, 'organization_id' => 10, 'role_in_project' => 'MEMBER'],
        ['user_id' => 6, 'organization_id' => 20, 'role_in_project' => 'MEMBER'],
    ];

    expect(qcc054TeamValidationErrors($members))
        ->toContain('Jumlah anggota tim maksimal 5 orang, termasuk leader.');
});

it('requires exactly one team leader', function (array $members) {
    expect(qcc054TeamValidationErrors($members))
        ->toContain('Tim harus memiliki tepat 1 leader.');
})->with([
    'no leader' => [[
        ['user_id' => 1, 'organization_id' => 10, 'role_in_project' => 'MEMBER'],
        ['user_id' => 2, 'organization_id' => 20, 'role_in_project' => 'MEMBER'],
    ]],
    'two leaders' => [[
        ['user_id' => 1, 'organization_id' => 10, 'role_in_project' => 'LEADER'],
        ['user_id' => 2, 'organization_id' => 20, 'role_in_project' => 'LEADER'],
    ]],
]);

it('requires team members from at least two LOBs', function () {
    $members = [
        ['user_id' => 1, 'organization_id' => 10, 'role_in_project' => 'LEADER'],
        ['user_id' => 2, 'organization_id' => 10, 'role_in_project' => 'MEMBER'],
    ];

    expect(qcc054TeamValidationErrors($members))
        ->toContain('Anggota tim harus berasal dari minimal 2 LOB/Divisi.');
});

it('rejects the same user appearing more than once', function () {
    $members = [
        ['user_id' => 1, 'organization_id' => 10, 'role_in_project' => 'LEADER'],
        ['user_id' => 1, 'organization_id' => 20, 'role_in_project' => 'MEMBER'],
    ];

    expect(qcc054TeamValidationErrors($members))
        ->toContain('Anggota yang sama tidak boleh dipilih lebih dari satu kali.');
});

it('reports every broken rule at once so the user can fix them in one go', function () {
    $members = [
        ['user_id' => 1, 'organization_id' => 10, 'role_in_project' => 'MEMBER'],
        ['user_id' => 1, 'organization_id' => 10, 'role_in_project' => 'MEMBER'],
    ];

    expect(qcc054TeamValidationErrors($members))->toHaveCount(3);
});

it('rejects an empty team with a single clear message', function () {
    expect(qcc054TeamValidationErrors([]))->toBe(['Tim belum memiliki anggota.']);
});

it('rejects a role that is neither leader nor member', function () {
    $members = [
        ['user_id' => 1, 'organization_id' => 10, 'role_in_project' => 'LEADER'],
        ['user_id' => 2, 'organization_id' => 20, 'role_in_project' => 'MENTOR'],
    ];

    expect(qcc054TeamValidationErrors($members))->toContain('Peran anggota hanya boleh Leader atau Anggota.');
});

it('does not count people without a LOB towards the two LOB minimum', function () {
    $members = [
        ['user_id' => 1, 'organization_id' => 10, 'role_in_project' => 'LEADER'],
        ['user_id' => 2, 'organization_id' => null, 'role_in_project' => 'MEMBER'],
        ['user_id' => 3, 'organization_id' => null, 'role_in_project' => 'MEMBER'],
    ];

    expect(qcc054TeamValidationErrors($members))->toContain('Anggota tim harus berasal dari minimal 2 LOB/Divisi.');
});

it('accepts a team of exactly two people from two LOBs', function () {
    $members = [
        ['user_id' => 1, 'organization_id' => 10, 'role_in_project' => 'LEADER'],
        ['user_id' => 2, 'organization_id' => 20, 'role_in_project' => 'MEMBER'],
    ];

    expect(fn () => (new TeamRuleValidator)->validate($members))->not->toThrow(ValidationException::class);
});
