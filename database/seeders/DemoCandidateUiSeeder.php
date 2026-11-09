<?php

namespace Database\Seeders;

use App\Enums\ProposalStatus;
use App\Models\Competency;
use App\Models\MemberCompetency;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class DemoCandidateUiSeeder extends Seeder
{
    use WithoutModelEvents;

    private const PROJECT_ID = 22;

    /**
     * Seed isolated candidate records for the current PRP-0022 UI test.
     */
    public function run(): void
    {
        $this->guardEnvironment();

        DB::transaction(function (): void {
            $project = Project::query()->findOrFail(self::PROJECT_ID);

            if (! in_array($project->proposal_status, [ProposalStatus::Draft, ProposalStatus::RevisionRequired], true)) {
                throw new \RuntimeException('PRP-0022 must remain editable before adding candidate demo data.');
            }

            $competencies = Competency::query()
                ->whereIn('code', ['C003', 'C005', 'C009'])
                ->where('is_active', true)
                ->get()
                ->keyBy('code');

            $requiredIds = $project->requirements()
                ->whereIn('competency_id', $competencies->pluck('id'))
                ->pluck('competency_id')
                ->map(fn (int $id): int => $id)
                ->all();

            if ($competencies->count() !== 3 || count($requiredIds) !== 3) {
                throw new \RuntimeException('PRP-0022 no longer has expected requirements C003, C005, and C009.');
            }

            $organizations = Organization::query()
                ->where('is_active', true)
                ->orderBy('id')
                ->limit(4)
                ->get();

            if ($organizations->count() < 2) {
                throw new \RuntimeException('At least two active organizations are required for candidate demo data.');
            }

            $leaderRole = Role::findByName('Team Leader', 'web');
            $memberRole = Role::findByName('SIMer / Member', 'web');

            $candidates = [
                [
                    'name' => '[UI DEMO] Rani Putri',
                    'role' => $leaderRole,
                    'organization' => $organizations[0],
                    'position' => 'Team Leader Operasional',
                    'levels' => ['C003' => 3, 'C005' => 2, 'C009' => 2],
                ],
                [
                    'name' => '[UI DEMO] Aditya Pranata',
                    'role' => $memberRole,
                    'organization' => $organizations[1],
                    'position' => 'Analis Proses',
                    'levels' => ['C003' => 2, 'C005' => 3, 'C009' => 1],
                ],
                [
                    'name' => '[UI DEMO] Sari Wulandari',
                    'role' => $memberRole,
                    'organization' => $organizations[$organizations->count() > 2 ? 2 : 0],
                    'position' => 'Staf Administrasi',
                    'levels' => ['C003' => 3, 'C009' => 1],
                ],
                [
                    'name' => '[UI DEMO] Bagas Mahendra',
                    'role' => $memberRole,
                    'organization' => $organizations[$organizations->count() > 3 ? 3 : 1],
                    'position' => 'Staf Layanan',
                    'levels' => ['C005' => 1],
                ],
            ];

            foreach ($candidates as $index => $candidateData) {
                $number = $index + 1;
                $user = User::query()->firstOrNew([
                    'email' => sprintf('ui-demo-qcc-%02d@example.test', $number),
                ]);
                $user->forceFill([
                    'name' => $candidateData['name'],
                    'password' => Hash::make(Str::random(48)),
                    'email_verified_at' => now(),
                    'organization_id' => $candidateData['organization']->id,
                    'position' => $candidateData['position'],
                    'work_location' => $candidateData['organization']->name,
                    'work_contact' => '000-UI-DEMO-'.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
                    'personal_contact' => '000-UI-TEST-'.str_pad((string) $number, 2, '0', STR_PAD_LEFT),
                    'availability_status' => 'AVAILABLE',
                    'needs_profile_setup' => false,
                    'is_active' => true,
                    'sim_id' => sprintf('UI-DEMO-%03d', $number),
                ])->save();
                $user->syncRoles([$candidateData['role']]);

                foreach ($candidateData['levels'] as $code => $selfLevel) {
                    $competency = $competencies->get($code);
                    $memberCompetency = MemberCompetency::query()->firstOrNew([
                        'user_id' => $user->id,
                        'competency_id' => $competency->id,
                    ]);
                    $memberCompetency->forceFill(['self_level' => $selfLevel])->save();
                }
            }

            $this->command?->info('Added four isolated UI demo candidates for PRP-0022. Existing proposal and team data were not changed.');
        });
    }

    private function guardEnvironment(): void
    {
        if (app()->environment('testing')) {
            return;
        }

        $connection = config('database.default');
        $host = config("database.connections.{$connection}.host");

        if (! app()->environment('local') || ! in_array($host, ['127.0.0.1', 'localhost', '::1'], true)) {
            throw new \RuntimeException('Candidate UI demo data may only be seeded into a local or test database.');
        }
    }
}
