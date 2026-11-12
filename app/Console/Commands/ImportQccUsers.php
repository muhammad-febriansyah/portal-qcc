<?php

namespace App\Console\Commands;

use App\Actions\Proposals\AssignOrganizationMentors;
use App\Models\Organization;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Spreadsheet\InvalidSpreadsheetException;
use App\Services\Spreadsheet\XlsxReader;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

#[Signature('qcc:users:import {path : Path file .xlsx (kolom: SIM ID, EMPLOYEE NAME, E-MAIL KANTOR, POSITION, opsional LOB/DIV dan MENTOR; semua sheet dibaca)} {--dry-run : Validasi dan tampilkan ringkasan tanpa menyimpan} {--create-organizations : Buat organisasi baru untuk LOB/DIV yang belum ada} {--deactivate-missing : Nonaktifkan akun yang tidak ada di file (akun Super Admin dan akun demo dibiarkan)}')]
#[Description('Impor akun portal dari file Excel data user. Akun baru memakai password awal "SIM" + nomor SIM ID. Kolom MENTOR menetapkan mentor LOB/DIV (pisahkan dua nama dengan "/").')]
class ImportQccUsers extends Command
{
    /** @var array<string, list<string>> Accepted header names for each column, compared in lowercase. */
    private const HEADER_ALIASES = [
        'sim_id' => ['sim id', 'sim_id'],
        'name' => ['employee name', 'nama', 'name'],
        'email' => ['e-mail kantor', 'email kantor', 'email'],
        'position' => ['position', 'posisi', 'role'],
    ];

    /** @var list<string> Optional mentor column headers, compared in lowercase. */
    private const MENTOR_HEADERS = ['mentor', 'mentor/reviewer', 'reviewer'];

    /** @var list<string> Optional organization column headers, compared in lowercase. */
    private const ORGANIZATION_HEADERS = ['lob/div', 'lob / div', 'organisasi', 'organization', 'divisi', 'lob'];

    /**
     * LOB/DIV names in the HR data that are written differently in the portal.
     *
     * @var array<string, string>
     */
    private const ORGANIZATION_ALIASES = [
        'LOB MSM LABOUR' => 'LOB MSM LO',
    ];

    /** @var array<string, string> Spreadsheet position mapped to the portal role. */
    private const ROLE_BY_POSITION = [
        'simers' => 'SIMer / Member',
        'simer' => 'SIMer / Member',
        'member' => 'SIMer / Member',
        'mentor/reviewer' => 'Mentor',
        'mentor' => 'Mentor',
        'qcc owner' => 'Owner QCC',
        'owner qcc' => 'Owner QCC',
    ];

    /**
     * Cyrillic letters that look like Latin ones and slip into names copied from other systems.
     *
     * @var array<string, string>
     */
    private const LOOKALIKE_LETTERS = [
        'А' => 'A', 'В' => 'B', 'Е' => 'E', 'К' => 'K', 'М' => 'M', 'Н' => 'H', 'О' => 'O',
        'Р' => 'P', 'С' => 'C', 'Т' => 'T', 'Х' => 'X', 'а' => 'a', 'е' => 'e', 'о' => 'o',
        'р' => 'p', 'с' => 'c', 'х' => 'x',
    ];

    public function handle(XlsxReader $reader, AuditService $audit): int
    {
        $path = $this->argument('path');

        if (! is_string($path) || ! is_file($path)) {
            $this->components->error('File tidak ditemukan.');

            return self::FAILURE;
        }

        try {
            $sheets = $reader->sheets($path);
        } catch (InvalidSpreadsheetException) {
            $this->components->error('File Excel tidak dapat dibaca. Simpan sebagai .xlsx.');

            return self::FAILURE;
        }

        $sheets = array_filter($sheets, static fn (array $rows): bool => $rows !== []);

        if ($sheets === []) {
            $this->components->error('File kosong.');

            return self::FAILURE;
        }

        $userSheets = [];

        foreach ($sheets as $sheetName => $rows) {
            $columns = $this->resolveColumns(array_shift($rows)['cells']);

            if ($columns !== null) {
                $userSheets[$sheetName] = [$columns, $rows];
            }
        }

        if ($userSheets === []) {
            $this->components->error('Header wajib: SIM ID, EMPLOYEE NAME, E-MAIL KANTOR, POSITION.');

            return self::FAILURE;
        }

        [$records, $errors] = $this->parseSheets($userSheets);
        [$mentorNames, $mentorErrors] = $this->mentorsByOrganization($records);
        $errors = [...$errors, ...$mentorErrors];

        if ($errors !== []) {
            foreach ($errors as $error) {
                $this->components->error($error);
            }

            $this->components->error('Impor dibatalkan. Tidak ada data yang disimpan.');

            return self::FAILURE;
        }

        $missingRoles = array_diff(
            array_unique(array_column($records, 'role')),
            Role::query()->pluck('name')->all(),
        );

        if ($missingRoles !== []) {
            $this->components->error('Role belum ada: '.implode(', ', $missingRoles).'. Jalankan db:seed lebih dulu.');

            return self::FAILURE;
        }

        // First pass only checks names, so a refused import leaves no new organization behind.
        if ($this->resolveOrganizations($records, create: false) === null) {
            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $this->summarize($records, $this->countExisting($records), $mentorNames);
            $this->components->info('Dry run: tidak ada data yang disimpan.');

            return self::SUCCESS;
        }

        $result = DB::transaction(function () use ($records, $audit, $mentorNames): array {
            $organizationIds = $this->resolveOrganizations($records, create: true) ?? [];

            foreach ($records as $position => $record) {
                $records[$position]['organization_id'] = $organizationIds[$this->organizationKey($record['organization'])] ?? null;
            }

            $result = $this->persist($records, $audit);
            $this->assignMentors($mentorNames, $records, $organizationIds);
            $result['deactivated'] = $this->option('deactivate-missing') ? $this->deactivateMissing($records) : 0;

            return $result;
        });

        $this->summarize($records, $result['updated'], $mentorNames);
        $this->components->info("Selesai: {$result['created']} akun baru, {$result['updated']} akun diperbarui, {$result['deactivated']} akun dinonaktifkan.");

        return self::SUCCESS;
    }

    /**
     * @param  list<string>  $headers
     * @return array<string, int>|null
     */
    private function resolveColumns(array $headers): ?array
    {
        $normalized = array_map(static fn (string $header): string => strtolower(trim($header)), $headers);
        $columns = [];

        foreach (self::HEADER_ALIASES as $key => $aliases) {
            $index = collect($normalized)->search(static fn (string $header): bool => in_array($header, $aliases, true));

            if ($index === false) {
                return null;
            }

            $columns[$key] = (int) $index;
        }

        $organizationIndex = collect($normalized)->search(static fn (string $header): bool => in_array($header, self::ORGANIZATION_HEADERS, true));

        if ($organizationIndex !== false) {
            $columns['organization'] = (int) $organizationIndex;
        }

        $mentorIndex = collect($normalized)->search(static fn (string $header): bool => in_array($header, self::MENTOR_HEADERS, true));

        if ($mentorIndex !== false) {
            $columns['mentor'] = (int) $mentorIndex;
        }

        return $columns;
    }

    /**
     * @param  array<string, array{0: array<string, int>, 1: list<array{row: int, cells: list<string>}>}>  $sheets
     * @return array{0: list<array{row: int, sheet: string, sim_id: string, name: string, email: string, role: string, organization: string, organization_id: int|null, mentors: list<string>}>, 1: list<string>}
     */
    private function parseSheets(array $sheets): array
    {
        $records = [];
        $errors = [];
        $named = count($sheets) > 1;

        foreach ($sheets as $sheetName => [$columns, $rows]) {
            [$sheetRecords, $sheetErrors] = $this->parseRows($rows, $columns, $named ? "Sheet {$sheetName} baris" : 'Baris', (string) $sheetName);
            $records = [...$records, ...$sheetRecords];
            $errors = [...$errors, ...$sheetErrors];
        }

        // The same person may appear on more than one sheet (a user list and a mentor list);
        // they are imported once, as long as the sheets agree on who they are.
        $merged = [];
        $simIdByEmail = [];

        foreach ($records as $record) {
            $existing = $merged[$record['sim_id']] ?? null;

            if ($existing === null) {
                $owner = $simIdByEmail[$record['email']] ?? null;

                if ($owner !== null) {
                    // Duplicates within one sheet are already reported by parseRows().
                    if ($owner['sheet'] !== $record['sheet']) {
                        $errors[] = "Sheet {$record['sheet']} baris {$record['row']}: email {$record['email']} dipakai SIM ID {$owner['sim_id']} di sheet {$owner['sheet']}.";
                    }

                    continue;
                }

                $merged[$record['sim_id']] = $record;
                $simIdByEmail[$record['email']] = ['sim_id' => $record['sim_id'], 'sheet' => $record['sheet']];

                continue;
            }

            if ($existing['sheet'] === $record['sheet']) {
                continue;
            }

            foreach (['email' => 'email', 'role' => 'posisi', 'organization' => 'LOB/DIV'] as $field => $label) {
                if ($existing[$field] !== '' && $record[$field] !== '' && $existing[$field] !== $record[$field]) {
                    $errors[] = "Sheet {$record['sheet']} baris {$record['row']}: {$label} SIM ID {$record['sim_id']} berbeda dengan sheet {$existing['sheet']}.";
                }
            }

            if ($existing['organization'] === '') {
                $merged[$record['sim_id']]['organization'] = $record['organization'];
            }

            $merged[$record['sim_id']]['mentors'] = array_values(array_unique([...$existing['mentors'], ...$record['mentors']]));
        }

        return [array_values($merged), $errors];
    }

    /**
     * @param  list<array{row: int, cells: list<string>}>  $rows
     * @param  array<string, int>  $columns
     * @return array{0: list<array{row: int, sheet: string, sim_id: string, name: string, email: string, role: string, organization: string, organization_id: int|null, mentors: list<string>}>, 1: list<string>}
     */
    private function parseRows(array $rows, array $columns, string $rowLabel, string $sheetName): array
    {
        $records = [];
        $errors = [];
        $seenSimIds = [];
        $seenEmails = [];

        foreach ($rows as $row) {
            // \p{Z} also strips non-breaking spaces that Excel cells often carry.
            $cell = static fn (string $key): string => (string) preg_replace('/^[\s\p{Z}]+|[\s\p{Z}]+$/u', '', $row['cells'][$columns[$key]] ?? '');

            $simId = (string) User::normalizeSimId($cell('sim_id'));
            $name = (string) preg_replace('/\s+/', ' ', strtr($cell('name'), self::LOOKALIKE_LETTERS));
            $email = strtolower($cell('email'));
            $position = strtolower($cell('position'));
            $organization = isset($columns['organization']) ? $cell('organization') : '';
            $mentors = isset($columns['mentor'])
                ? array_values(array_filter(array_map(fn (string $mentor): string => $this->personKey($mentor), explode('/', $cell('mentor')))))
                : [];

            $validator = Validator::make(
                ['sim_id' => $simId, 'name' => $name, 'email' => $email],
                [
                    'sim_id' => ['required', 'string', 'max:32', 'regex:/^[A-Z0-9._-]+$/'],
                    'name' => ['required', 'string', 'max:255'],
                    'email' => ['required', 'email:rfc', 'max:255'],
                ],
            );

            foreach ($validator->errors()->all() as $message) {
                $errors[] = "{$rowLabel} {$row['row']}: {$message}";
            }

            if (! isset(self::ROLE_BY_POSITION[$position])) {
                $errors[] = "{$rowLabel} {$row['row']}: posisi \"{$cell('position')}\" tidak dikenal.";
            }

            if (isset($seenSimIds[$simId])) {
                $errors[] = "{$rowLabel} {$row['row']}: SIM ID {$simId} duplikat dengan baris {$seenSimIds[$simId]}.";
            }

            if (isset($seenEmails[$email])) {
                $errors[] = "{$rowLabel} {$row['row']}: email {$email} duplikat dengan baris {$seenEmails[$email]}.";
            }

            $seenSimIds[$simId] = $row['row'];
            $seenEmails[$email] = $row['row'];

            $records[] = [
                'row' => $row['row'],
                'sheet' => $sheetName,
                'sim_id' => $simId,
                'name' => $name,
                'email' => $email,
                'role' => self::ROLE_BY_POSITION[$position] ?? '',
                'organization' => $organization,
                'organization_id' => null,
                'mentors' => $mentors,
            ];
        }

        return [$records, $errors];
    }

    /**
     * Mentor names given in the MENTOR column, gathered per LOB/DIV. Excel often merges this
     * cell over a whole LOB, so it only needs to appear on one row of each LOB/DIV. Every name
     * must be a person in the file or an existing account.
     *
     * @param  list<array{row: int, sheet: string, sim_id: string, name: string, email: string, role: string, organization: string, organization_id: int|null, mentors: list<string>}>  $records
     * @return array{0: array<string, list<string>>, 1: list<string>}
     */
    private function mentorsByOrganization(array $records): array
    {
        $mentorNames = [];
        $errors = [];

        foreach ($records as $record) {
            if ($record['mentors'] === []) {
                continue;
            }

            if ($record['organization'] === '') {
                $errors[] = "Sheet {$record['sheet']} baris {$record['row']}: mentor diisi tetapi LOB/DIV kosong.";

                continue;
            }

            $key = $this->organizationKey($record['organization']);
            $mentorNames[$key] = array_values(array_unique([...($mentorNames[$key] ?? []), ...$record['mentors']]));
        }

        $namesInFile = collect($records)->map(fn (array $record): string => $this->personKey($record['name']))->all();

        foreach ($mentorNames as $organization => $names) {
            foreach ($names as $name) {
                $known = in_array($name, $namesInFile, true)
                    || User::query()->whereRaw('UPPER(name) = ?', [$name])->exists();

                if (! $known) {
                    $errors[] = "Mentor \"{$name}\" untuk {$organization} tidak ditemukan di file maupun di portal.";
                }
            }
        }

        return [$mentorNames, $errors];
    }

    /**
     * Replace the mentors of every LOB/DIV named in the file. Mentors are matched by name,
     * preferring the person imported from this file.
     *
     * @param  array<string, list<string>>  $mentorNames
     * @param  list<array{row: int, sheet: string, sim_id: string, name: string, email: string, role: string, organization: string, organization_id: int|null, mentors: list<string>}>  $records
     * @param  array<string, int>  $organizationIds
     */
    private function assignMentors(array $mentorNames, array $records, array $organizationIds): void
    {
        $simIdByName = collect($records)->mapWithKeys(fn (array $record): array => [$this->personKey($record['name']) => $record['sim_id']]);
        $assign = app(AssignOrganizationMentors::class);

        foreach ($mentorNames as $organizationKey => $names) {
            $organization = isset($organizationIds[$organizationKey]) ? Organization::query()->find($organizationIds[$organizationKey]) : null;

            if ($organization === null) {
                continue;
            }

            $mentors = collect($names)->map(fn (string $name): ?User => $simIdByName->has($name)
                ? User::query()->where('sim_id', $simIdByName->get($name))->first()
                : User::query()->whereRaw('UPPER(name) = ?', [$name])->first())
                ->filter();

            $organization->mentors()->sync($mentors->pluck('id')->all());
            $mentors->each(fn (User $mentor) => AssignOrganizationMentors::grantMentorRole($mentor));
            $assign->backfill($organization);
        }
    }

    /**
     * A person's name as compared between the user list and the MENTOR column.
     */
    private function personKey(string $name): string
    {
        return strtoupper(trim((string) preg_replace('/[\s\p{Z}]+/u', ' ', strtr($name, self::LOOKALIKE_LETTERS))));
    }

    /**
     * Match each LOB/DIV to a portal organization by name, also trying the "DIV " and "LOB "
     * prefixes the portal uses. Unknown ones stop the import unless --create-organizations is given.
     *
     * @param  list<array{row: int, sheet: string, sim_id: string, name: string, email: string, role: string, organization: string, organization_id: int|null, mentors: list<string>}>  $records
     * @return array<string, int>|null
     */
    private function resolveOrganizations(array $records, bool $create): ?array
    {
        $wanted = collect($records)->pluck('organization')->filter()->unique()->values();

        if ($wanted->isEmpty()) {
            return [];
        }

        $existing = Organization::query()->get(['id', 'name'])
            ->mapWithKeys(fn (Organization $organization): array => [$this->organizationKey($organization->name) => $organization->id]);
        $resolved = [];
        $missing = [];

        foreach ($wanted as $name) {
            $key = $this->organizationKey($name);
            $target = $this->organizationKey(self::ORGANIZATION_ALIASES[$key] ?? $key);
            $id = $existing->get($target) ?? $existing->get('DIV '.$target) ?? $existing->get('LOB '.$target);

            if ($id === null) {
                $missing[] = $name;

                continue;
            }

            $resolved[$key] = $id;
        }

        if ($missing === []) {
            return $resolved;
        }

        if (! $this->option('create-organizations')) {
            $this->components->error('LOB/DIV belum ada di portal: '.implode(', ', $missing).'. Tambahkan --create-organizations untuk membuatnya.');

            return null;
        }

        foreach ($missing as $name) {
            if (! $create) {
                $this->components->twoColumnDetail('Organisasi baru', $name);

                continue;
            }

            $organization = Organization::query()->create([
                'code' => Str::slug($name),
                'name' => $this->organizationKey($name),
                'is_active' => true,
            ]);
            $resolved[$this->organizationKey($name)] = $organization->id;
        }

        return $resolved;
    }

    private function organizationKey(string $name): string
    {
        return trim((string) preg_replace('/\s+/', ' ', str_replace('&', ' ', strtoupper($name))));
    }

    /**
     * @param  list<array{row: int, sheet: string, sim_id: string, name: string, email: string, role: string, organization: string, organization_id: int|null, mentors: list<string>}>  $records
     */
    private function countExisting(array $records): int
    {
        return User::query()
            ->whereIn('sim_id', array_column($records, 'sim_id'))
            ->orWhereIn('email', array_column($records, 'email'))
            ->count();
    }

    /**
     * Existing accounts keep their password; only identity fields and role are refreshed.
     *
     * @param  list<array{row: int, sheet: string, sim_id: string, name: string, email: string, role: string, organization: string, organization_id: int|null, mentors: list<string>}>  $records
     * @return array{created: int, updated: int}
     */
    private function persist(array $records, AuditService $audit): array
    {
        $created = 0;
        $updated = 0;

        foreach ($records as $record) {
            $user = User::query()->where('sim_id', $record['sim_id'])->first()
                ?? User::query()->where('email', $record['email'])->first();

            $previousRole = $user?->getRoleNames()->sort()->first();

            if ($user === null) {
                $user = new User([
                    'name' => $record['name'],
                    'sim_id' => $record['sim_id'],
                    'email' => $record['email'],
                    'password' => User::initialPasswordFor($record['sim_id']),
                    'organization_id' => $record['organization_id'],
                    'needs_profile_setup' => true,
                ]);
                $user->email_verified_at = now();
                $user->is_active = true;
                $user->save();
                $created++;
            } else {
                $user->update([
                    'name' => $record['name'],
                    'sim_id' => $record['sim_id'],
                    'email' => $record['email'],
                    ...($record['organization_id'] !== null ? ['organization_id' => $record['organization_id']] : []),
                ]);
                $updated++;
            }

            $user->syncRoles([$record['role']]);

            if ($previousRole !== $record['role']) {
                $audit->record('user.role_changed', $user, before: ['role' => $previousRole], after: ['role' => $record['role']], reason: 'Impor data user');
            }
        }

        return ['created' => $created, 'updated' => $updated];
    }

    /**
     * @param  list<array{row: int, sheet: string, sim_id: string, name: string, email: string, role: string, organization: string, organization_id: int|null, mentors: list<string>}>  $records
     */
    /**
     * Active portal accounts that are not in the file, leaving out demo accounts and Super Admins
     * so nobody is locked out of administration.
     *
     * @param  list<array{row: int, sheet: string, sim_id: string, name: string, email: string, role: string, organization: string, organization_id: int|null, mentors: list<string>}>  $records
     * @return Builder<User>
     */
    private function accountsMissingFromFile(array $records): Builder
    {
        return User::query()
            ->where('is_active', true)
            ->where(fn ($query) => $query->whereNull('sim_id')->orWhereNotIn('sim_id', array_column($records, 'sim_id')))
            ->whereNotIn('email', array_column($records, 'email'))
            ->where('email', 'not like', '%@example.test')
            ->whereDoesntHave('roles', fn ($roles) => $roles->where('name', 'Super Admin'));
    }

    /**
     * @param  list<array{row: int, sheet: string, sim_id: string, name: string, email: string, role: string, organization: string, organization_id: int|null, mentors: list<string>}>  $records
     */
    private function deactivateMissing(array $records): int
    {
        $users = $this->accountsMissingFromFile($records)->get();
        $users->each(fn (User $user) => $user->forceFill(['is_active' => false])->save());

        return $users->count();
    }

    /**
     * @param  array<string, list<string>>  $mentorNames
     */
    private function summarize(array $records, int $existing, array $mentorNames = []): void
    {
        $this->table(
            ['Role', 'Jumlah'],
            collect($records)->countBy('role')->map(fn (int $count, string $role): array => [$role, $count])->values()->all(),
        );
        $this->components->twoColumnDetail('Total baris', (string) count($records));
        $this->components->twoColumnDetail('Akun sudah ada (diperbarui)', (string) $existing);

        if ($mentorNames !== []) {
            $this->table(
                ['LOB/DIV', 'Mentor'],
                collect($mentorNames)->map(fn (array $names, string $organization): array => [$organization, implode(' / ', $names)])->values()->all(),
            );
        }

        $missing = $this->accountsMissingFromFile($records)->orderBy('name')->pluck('name');

        if ($missing->isNotEmpty()) {
            $this->components->warn(($this->option('deactivate-missing') && ! $this->option('dry-run') ? '' : 'Tanpa --deactivate-missing tidak diubah. ')
                ."{$missing->count()} akun aktif di portal tidak ada di file: ".$missing->implode(', '));
        }
    }
}
