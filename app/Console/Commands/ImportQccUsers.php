<?php

namespace App\Console\Commands;

use App\Models\Organization;
use App\Models\User;
use App\Services\AuditService;
use App\Services\Spreadsheet\InvalidSpreadsheetException;
use App\Services\Spreadsheet\XlsxReader;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

#[Signature('qcc:users:import {path : Path file .xlsx (kolom: SIM ID, EMPLOYEE NAME, E-MAIL KANTOR, POSITION, opsional LOB/DIV)} {--dry-run : Validasi dan tampilkan ringkasan tanpa menyimpan} {--create-organizations : Buat organisasi baru untuk LOB/DIV yang belum ada}')]
#[Description('Impor akun portal dari file Excel data user. Akun baru memakai password awal SIM ID.')]
class ImportQccUsers extends Command
{
    /** @var array<string, list<string>> Accepted header names for each column, compared in lowercase. */
    private const HEADER_ALIASES = [
        'sim_id' => ['sim id', 'sim_id'],
        'name' => ['employee name', 'nama', 'name'],
        'email' => ['e-mail kantor', 'email kantor', 'email'],
        'position' => ['position', 'posisi', 'role'],
    ];

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
            $rows = $reader->firstSheetRows($path);
        } catch (InvalidSpreadsheetException) {
            $this->components->error('File Excel tidak dapat dibaca. Simpan sebagai .xlsx.');

            return self::FAILURE;
        }

        if ($rows === []) {
            $this->components->error('File kosong.');

            return self::FAILURE;
        }

        $columns = $this->resolveColumns(array_shift($rows)['cells']);

        if ($columns === null) {
            $this->components->error('Header wajib: SIM ID, EMPLOYEE NAME, E-MAIL KANTOR, POSITION.');

            return self::FAILURE;
        }

        [$records, $errors] = $this->parseRows($rows, $columns);

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
            $this->summarize($records, $this->countExisting($records));
            $this->components->info('Dry run: tidak ada data yang disimpan.');

            return self::SUCCESS;
        }

        $result = DB::transaction(function () use ($records, $audit): array {
            $organizationIds = $this->resolveOrganizations($records, create: true) ?? [];

            foreach ($records as $position => $record) {
                $records[$position]['organization_id'] = $organizationIds[$this->organizationKey($record['organization'])] ?? null;
            }

            return $this->persist($records, $audit);
        });

        $this->summarize($records, $result['updated']);
        $this->components->info("Selesai: {$result['created']} akun baru, {$result['updated']} akun diperbarui.");

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

        return $columns;
    }

    /**
     * @param  list<array{row: int, cells: list<string>}>  $rows
     * @param  array<string, int>  $columns
     * @return array{0: list<array{row: int, sim_id: string, name: string, email: string, role: string, organization: string, organization_id: int|null}>, 1: list<string>}
     */
    private function parseRows(array $rows, array $columns): array
    {
        $records = [];
        $errors = [];
        $seenSimIds = [];
        $seenEmails = [];

        foreach ($rows as $row) {
            // \p{Z} also strips non-breaking spaces that Excel cells often carry.
            $cell = static fn (string $key): string => (string) preg_replace('/^[\s\p{Z}]+|[\s\p{Z}]+$/u', '', $row['cells'][$columns[$key]] ?? '');

            $simId = strtoupper($cell('sim_id'));
            $simId = preg_match('/^\d+$/', $simId) === 1 ? 'SIM'.$simId : $simId;
            $name = (string) preg_replace('/\s+/', ' ', strtr($cell('name'), self::LOOKALIKE_LETTERS));
            $email = strtolower($cell('email'));
            $position = strtolower($cell('position'));
            $organization = isset($columns['organization']) ? $cell('organization') : '';

            $validator = Validator::make(
                ['sim_id' => $simId, 'name' => $name, 'email' => $email],
                [
                    'sim_id' => ['required', 'string', 'max:32', 'regex:/^[A-Z0-9._-]+$/'],
                    'name' => ['required', 'string', 'max:255'],
                    'email' => ['required', 'email:rfc', 'max:255'],
                ],
            );

            foreach ($validator->errors()->all() as $message) {
                $errors[] = "Baris {$row['row']}: {$message}";
            }

            if (! isset(self::ROLE_BY_POSITION[$position])) {
                $errors[] = "Baris {$row['row']}: posisi \"{$cell('position')}\" tidak dikenal.";
            }

            if (isset($seenSimIds[$simId])) {
                $errors[] = "Baris {$row['row']}: SIM ID {$simId} duplikat dengan baris {$seenSimIds[$simId]}.";
            }

            if (isset($seenEmails[$email])) {
                $errors[] = "Baris {$row['row']}: email {$email} duplikat dengan baris {$seenEmails[$email]}.";
            }

            $seenSimIds[$simId] = $row['row'];
            $seenEmails[$email] = $row['row'];

            $records[] = [
                'row' => $row['row'],
                'sim_id' => $simId,
                'name' => $name,
                'email' => $email,
                'role' => self::ROLE_BY_POSITION[$position] ?? '',
                'organization' => $organization,
                'organization_id' => null,
            ];
        }

        return [$records, $errors];
    }

    /**
     * Match each LOB/DIV to a portal organization by name, also trying the "DIV " and "LOB "
     * prefixes the portal uses. Unknown ones stop the import unless --create-organizations is given.
     *
     * @param  list<array{row: int, sim_id: string, name: string, email: string, role: string, organization: string, organization_id: int|null}>  $records
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
     * @param  list<array{row: int, sim_id: string, name: string, email: string, role: string, organization: string, organization_id: int|null}>  $records
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
     * @param  list<array{row: int, sim_id: string, name: string, email: string, role: string, organization: string, organization_id: int|null}>  $records
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
                    'password' => $record['sim_id'],
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
     * @param  list<array{row: int, sim_id: string, name: string, email: string, role: string, organization: string, organization_id: int|null}>  $records
     */
    private function summarize(array $records, int $existing): void
    {
        $this->table(
            ['Role', 'Jumlah'],
            collect($records)->countBy('role')->map(fn (int $count, string $role): array => [$role, $count])->values()->all(),
        );
        $this->components->twoColumnDetail('Total baris', (string) count($records));
        $this->components->twoColumnDetail('Akun sudah ada (diperbarui)', (string) $existing);
    }
}
