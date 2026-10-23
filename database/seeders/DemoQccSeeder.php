<?php

namespace Database\Seeders;

use App\Actions\Proposals\InitializeProjectSteps;
use App\Enums\ActionStatus;
use App\Enums\BatchStatus;
use App\Enums\ProposalStatus;
use App\Enums\ReportStatus;
use App\Enums\RiskLevel;
use App\Enums\RiskStatus;
use App\Enums\RiskType;
use App\Enums\StepStatus;
use App\Models\ActionItem;
use App\Models\BiweeklyReport;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ProjectEvidence;
use App\Models\ProjectMentor;
use App\Models\ProjectRisk;
use App\Models\ProjectStep;
use App\Models\QccBatch;
use App\Models\ReviewAssignment;
use App\Models\User;
use App\Services\EvidenceFileInspector;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role;

class DemoQccSeeder extends Seeder
{
    private const ROLE_NAMES = [
        'Super Admin',
        'Owner QCC',
        'Mentor',
        'Team Leader',
        'SIMer / Member',
    ];

    private const PEOPLE = [
        'Andi Pratama', 'Budi Santoso', 'Citra Lestari', 'Dewi Anggraini', 'Eko Saputra',
        'Fajar Nugroho', 'Gita Maharani', 'Hendra Wijaya', 'Intan Permata', 'Joko Setiawan',
        'Kartika Putri', 'Lukman Hakim', 'Maya Sari', 'Nanda Kurniawan', 'Rina Oktaviani',
    ];

    /** @var list<array{title: string, theme: string, background: string, problem: string, current: string, baseline: string, target: string, scope: string}> */
    private const PROJECTS = [
        ['title' => 'Mempercepat proses verifikasi dokumen pelanggan', 'theme' => 'Percepatan verifikasi dokumen', 'background' => 'Peningkatan volume pengajuan membuat waktu verifikasi perlu dikendalikan.', 'problem' => 'Sebagian dokumen pelanggan selesai diverifikasi lebih dari dua hari kerja.', 'current' => 'Waktu rata-rata verifikasi mencapai 2,8 hari kerja berdasarkan rekap operasional.', 'baseline' => '2,8 hari kerja per pengajuan', 'target' => 'Mencapai rata-rata maksimal 1,5 hari kerja dalam satu batch.', 'scope' => 'Tim verifikasi dokumen pada unit layanan.'],
        ['title' => 'Mengurangi kesalahan input data transaksi', 'theme' => 'Akurasi input transaksi', 'background' => 'Data transaksi yang akurat membantu unit lain menindaklanjuti proses tanpa koreksi berulang.', 'problem' => 'Transaksi yang perlu dikoreksi masih muncul setiap minggu.', 'current' => 'Rata-rata 18 koreksi ditemukan dari 600 transaksi per minggu.', 'baseline' => '3% transaksi memerlukan koreksi', 'target' => 'Menurunkan koreksi menjadi maksimal 1% dalam tiga bulan.', 'scope' => 'Proses input transaksi pada tim administrasi.'],
        ['title' => 'Mempercepat tindak lanjut permintaan layanan internal', 'theme' => 'Waktu respons layanan internal', 'background' => 'Unit pemohon membutuhkan kepastian status permintaan layanan.', 'problem' => 'Permintaan layanan belum selalu memperoleh respons pada hari yang sama.', 'current' => 'Median respons awal tercatat 9 jam kerja.', 'baseline' => '9 jam kerja untuk respons awal', 'target' => 'Mencapai median respons awal maksimal 4 jam kerja.', 'scope' => 'Permintaan layanan internal kategori reguler.'],
        ['title' => 'Menurunkan waktu tunggu proses rekonsiliasi', 'theme' => 'Efisiensi rekonsiliasi', 'background' => 'Rekonsiliasi yang selesai tepat waktu menjaga kelancaran laporan bulanan.', 'problem' => 'Data pendukung terlambat diterima sehingga rekonsiliasi menumpuk di akhir periode.', 'current' => 'Penyelesaian rata-rata membutuhkan 4 hari kerja.', 'baseline' => '4 hari kerja per periode', 'target' => 'Menurunkan waktu penyelesaian menjadi 2 hari kerja.', 'scope' => 'Rekonsiliasi rutin periode bulanan.'],
        ['title' => 'Meningkatkan ketepatan jadwal pemeliharaan aset', 'theme' => 'Kepatuhan pemeliharaan aset', 'background' => 'Pemeliharaan terjadwal membantu menjaga ketersediaan aset operasional.', 'problem' => 'Sebagian jadwal pemeliharaan bergeser karena pengingat belum terpusat.', 'current' => 'Kepatuhan jadwal pemeliharaan tercatat 82%.', 'baseline' => '82% pekerjaan sesuai jadwal', 'target' => 'Meningkatkan kepatuhan menjadi minimal 95%.', 'scope' => 'Aset operasional prioritas unit.'],
        ['title' => 'Mengurangi pengembalian berkas pengadaan', 'theme' => 'Kelengkapan berkas pengadaan', 'background' => 'Berkas yang lengkap sejak awal mempercepat pemeriksaan pengadaan.', 'problem' => 'Berkas pengajuan sering dikembalikan untuk melengkapi dokumen.', 'current' => 'Sekitar 14 dari 100 berkas dikembalikan.', 'baseline' => '14% berkas dikembalikan', 'target' => 'Menurunkan pengembalian berkas menjadi maksimal 5%.', 'scope' => 'Pemeriksaan kelengkapan berkas pengadaan.'],
        ['title' => 'Meningkatkan akurasi pencatatan stok operasional', 'theme' => 'Akurasi stok operasional', 'background' => 'Catatan stok yang akurat mengurangi selisih saat pengadaan ulang.', 'problem' => 'Pencatatan stok dan jumlah fisik belum selalu sesuai.', 'current' => 'Selisih ditemukan pada 7% item yang diperiksa.', 'baseline' => '7% item memiliki selisih', 'target' => 'Menurunkan selisih menjadi maksimal 2%.', 'scope' => 'Item operasional yang tercatat dalam inventaris unit.'],
        ['title' => 'Memangkas waktu penyusunan laporan bulanan', 'theme' => 'Efisiensi pelaporan bulanan', 'background' => 'Laporan bulanan menjadi dasar evaluasi kinerja dan perencanaan.', 'problem' => 'Pengumpulan data manual memperpanjang waktu penyusunan laporan.', 'current' => 'Laporan selesai rata-rata 6 hari kerja setelah periode berakhir.', 'baseline' => '6 hari kerja', 'target' => 'Menyelesaikan laporan maksimal 3 hari kerja.', 'scope' => 'Kompilasi laporan operasional bulanan.'],
        ['title' => 'Mengurangi gangguan berulang pada perangkat kerja', 'theme' => 'Keandalan perangkat kerja', 'background' => 'Perangkat yang stabil mendukung produktivitas pegawai.', 'problem' => 'Gangguan berulang pada perangkat yang sama meningkatkan waktu henti.', 'current' => 'Terdapat 12 tiket gangguan berulang per bulan.', 'baseline' => '12 tiket per bulan', 'target' => 'Mengurangi tiket gangguan berulang menjadi maksimal 5.', 'scope' => 'Perangkat kerja yang dikelola tim dukungan.'],
        ['title' => 'Meningkatkan ketepatan pemenuhan permintaan barang', 'theme' => 'Ketepatan pemenuhan barang', 'background' => 'Permintaan barang yang terpenuhi sesuai jadwal mendukung kegiatan unit.', 'problem' => 'Sebagian permintaan barang diterima melewati tanggal kebutuhan.', 'current' => 'Ketepatan pemenuhan tercatat 78%.', 'baseline' => '78% permintaan tepat waktu', 'target' => 'Mencapai ketepatan pemenuhan minimal 93%.', 'scope' => 'Permintaan barang rutin internal.'],
        ['title' => 'Mengurangi waktu antrean layanan administrasi', 'theme' => 'Waktu antrean administrasi', 'background' => 'Waktu layanan yang terukur meningkatkan pengalaman pengguna internal.', 'problem' => 'Antrean meningkat pada jam sibuk dan memengaruhi waktu penyelesaian.', 'current' => 'Waktu tunggu rata-rata 22 menit.', 'baseline' => '22 menit per pengguna', 'target' => 'Menurunkan waktu tunggu menjadi maksimal 12 menit.', 'scope' => 'Layanan administrasi pada jam kerja.'],
        ['title' => 'Meningkatkan ketepatan pengarsipan dokumen digital', 'theme' => 'Kerapian arsip digital', 'background' => 'Arsip digital yang konsisten memudahkan pencarian dan audit.', 'problem' => 'Dokumen sulit ditemukan karena nama berkas dan folder belum seragam.', 'current' => 'Rata-rata pencarian dokumen membutuhkan 11 menit.', 'baseline' => '11 menit per pencarian', 'target' => 'Menurunkan waktu pencarian menjadi maksimal 4 menit.', 'scope' => 'Arsip digital dokumen kerja aktif.'],
        ['title' => 'Menurunkan keterlambatan pengiriman laporan unit', 'theme' => 'Ketepatan waktu laporan unit', 'background' => 'Laporan tepat waktu membantu konsolidasi kinerja perusahaan.', 'problem' => 'Laporan dari beberapa unit masuk setelah batas waktu.', 'current' => 'Ketepatan waktu pelaporan tercatat 84%.', 'baseline' => '84% laporan tepat waktu', 'target' => 'Meningkatkan ketepatan menjadi minimal 97%.', 'scope' => 'Laporan rutin dari unit terkait.'],
        ['title' => 'Mengurangi pemakaian material sekali pakai', 'theme' => 'Efisiensi material', 'background' => 'Pemakaian material yang terkendali menekan pemborosan operasional.', 'problem' => 'Pemakaian material sekali pakai belum dipantau per kegiatan.', 'current' => 'Pemakaian rata-rata mencapai 1.200 unit per bulan.', 'baseline' => '1.200 unit per bulan', 'target' => 'Mengurangi pemakaian minimal 15% tanpa mengganggu layanan.', 'scope' => 'Kegiatan operasional kantor yang memakai material terkait.'],
        ['title' => 'Meningkatkan ketepatan pembaruan data pegawai', 'theme' => 'Kelengkapan data pegawai', 'background' => 'Data pegawai yang mutakhir mendukung administrasi dan layanan SDM.', 'problem' => 'Pembaruan data belum selalu dilakukan segera setelah perubahan.', 'current' => 'Kelengkapan data mutakhir pada pemeriksaan terakhir 89%.', 'baseline' => '89% data mutakhir', 'target' => 'Mencapai kelengkapan data mutakhir minimal 98%.', 'scope' => 'Data profil pegawai pada unit terkait.'],
    ];

    public function run(): void
    {
        $this->guardEnvironment();

        $this->call([
            AdminPermissionSeeder::class,
            ProjectAccessSeeder::class,
            OrganizationAndCompetencyGroupSeeder::class,
        ]);

        Model::withoutEvents(function (): void {
            DB::transaction(function (): void {
                $organizations = Organization::query()->orderBy('id')->get();
                $users = $this->seedUsers($organizations);
                $batches = $this->seedBatches();
                $projects = $this->seedProjects($users, $batches, $organizations);
                $approvedProjects = $projects->filter(fn (Project $project): bool => $project->proposal_status === ProposalStatus::Approved)->values();

                $this->seedWorkflowAndTeams($projects, $users);
                $this->seedSupportingData($approvedProjects, $projects, $users);
            });
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
            throw new \RuntimeException('Demo QCC data may only be seeded into a local or test database.');
        }
    }

    /** @param Collection<int, Organization> $organizations
     * @return array<string, list<User>>
     */
    private function seedUsers($organizations): array
    {
        $usersByRole = [];
        $roleSlugs = [
            'Super Admin' => 'super-admin',
            'Owner QCC' => 'owner-qcc',
            'Mentor' => 'mentor',
            'Team Leader' => 'team-leader',
            'SIMer / Member' => 'simer-member',
        ];
        $positions = [
            'Super Admin' => 'Administrator Sistem',
            'Owner QCC' => 'Koordinator QCC',
            'Mentor' => 'Mentor Continuous Improvement',
            'Team Leader' => 'Team Leader QCC',
            'SIMer / Member' => 'Anggota QCC',
        ];
        $organizationList = $organizations->values();

        foreach (self::ROLE_NAMES as $roleName) {
            $role = Role::findByName($roleName, 'web');
            $usersByRole[$roleName] = [];

            foreach (self::PEOPLE as $index => $personName) {
                $number = $index + 1;
                $email = sprintf('demo.%s.%02d@example.test', $roleSlugs[$roleName], $number);
                $organization = $organizationList[($index + count($usersByRole)) % $organizationList->count()];
                $user = User::query()->firstOrNew(['email' => $email]);
                $isNew = ! $user->exists;
                $user->forceFill([
                    'name' => sprintf('%s %02d · %s', str_replace(' / ', '', $roleName), $number, $personName),
                    'email' => $email,
                    'email_verified_at' => '2026-01-05 09:00:00',
                    'organization_id' => $organization->id,
                    'sim_id' => sprintf('DEMO-%s-%03d', strtoupper(str_replace('-', '', $roleSlugs[$roleName])), $number),
                    'position' => $positions[$roleName],
                    'work_location' => ['Jakarta', 'Bandung', 'Surabaya', 'Semarang', 'Medan'][$index % 5],
                    'availability_status' => $index < 3 ? 'AVAILABLE' : (in_array($roleName, ['Team Leader', 'SIMer / Member'], true) ? 'AVAILABLE' : ['AVAILABLE', 'UNAVAILABLE', 'AVAILABLE'][$index % 3]),
                    'is_active' => true,
                ]);

                if ($isNew || ! Hash::check('password', (string) $user->password)) {
                    $user->password = Hash::make('password');
                }

                $user->save();
                $user->syncRoles([$role]);
                $usersByRole[$roleName][] = $user;
            }
        }

        return $usersByRole;
    }

    /** @return list<QccBatch> */
    private function seedBatches(): array
    {
        $definitions = [
            ['code' => 'DEMO-QCC-2026-01', 'name' => 'Batch QCC 2026 · Selesai', 'starts_on' => '2026-01-05', 'ends_on' => '2026-04-30', 'status' => BatchStatus::Closed],
            ['code' => 'DEMO-QCC-2026-02', 'name' => 'Batch QCC 2026 · Berjalan', 'starts_on' => '2026-07-01', 'ends_on' => '2026-12-31', 'status' => BatchStatus::Active],
            ['code' => 'DEMO-QCC-2026-03', 'name' => 'Batch QCC 2026 · Gelombang 3', 'starts_on' => '2026-08-03', 'ends_on' => '2027-02-28', 'status' => BatchStatus::Active],
        ];

        return array_map(fn (array $batch): QccBatch => QccBatch::query()->updateOrCreate(
            ['code' => $batch['code']],
            ['name' => $batch['name'], 'starts_on' => $batch['starts_on'], 'ends_on' => $batch['ends_on'], 'status' => $batch['status']],
        ), $definitions);
    }

    /** @param array<string, list<User>> $users
     * @param  list<QccBatch>  $batches
     * @param  Collection<int, Organization>  $organizations
     * @return Collection<int, Project>
     */
    private function seedProjects(array $users, array $batches, $organizations)
    {
        $statuses = [
            ProposalStatus::Approved, ProposalStatus::Approved, ProposalStatus::Rejected, ProposalStatus::Rejected, ProposalStatus::RevisionRequired,
            ProposalStatus::Approved, ProposalStatus::UnderReview, ProposalStatus::UnderReview, ProposalStatus::Submitted, ProposalStatus::Submitted,
            ProposalStatus::RevisionRequired, ProposalStatus::RevisionRequired, ProposalStatus::Draft, ProposalStatus::Draft, ProposalStatus::Draft,
        ];
        $projects = collect();

        foreach (self::PROJECTS as $index => $description) {
            $number = $index + 1;
            $status = $statuses[$index];
            $batch = $batches[intdiv($index, 5)];
            $leader = $users['Team Leader'][$index];
            $member = $users['SIMer / Member'][$index];
            $submittedAt = $status === ProposalStatus::Draft ? null : now()->subDays(55 - $index * 2)->setTime(9, 0);
            $approvedAt = $status === ProposalStatus::Approved ? now()->subDays(35 - $index)->setTime(15, 0) : null;
            $project = Project::query()->where('title', '[DEMO] '.$description['title'])->first() ?? new Project;
            $project->forceFill([
                'title' => '[DEMO] '.$description['title'],
                'batch_id' => $batch->id,
                'proposer_id' => $leader->id,
                'pic_id' => $leader->id,
                'theme' => $description['theme'],
                'background' => $description['background'],
                'problem_statement' => $description['problem'],
                'current_situation' => $description['current'],
                'baseline' => $description['baseline'],
                'target' => $description['target'],
                'scope' => $description['scope'],
                'learning_goals_json' => ['Analisis proses', 'Kolaborasi lintas fungsi', 'Pengukuran hasil perbaikan'],
                'rca' => 'Tim mengumpulkan data proses, mengelompokkan penyebab, lalu memvalidasi penyebab dominan dengan unit terkait.',
                'improvement_plan' => 'Uji coba perbaikan dilakukan bertahap, hasilnya dibandingkan dengan baseline, lalu prosedur diperbarui bila efektif.',
                'business_benefit' => 'Mengurangi waktu tunggu dan pekerjaan ulang serta meningkatkan konsistensi layanan.',
                'proposal_status' => $status,
                'submitted_at' => $submittedAt,
                'approved_at' => $approvedAt,
                'wizard_step' => $status === ProposalStatus::Draft ? 3 : 5,
            ])->save();

            $leaderOrganization = $organizations[($index * 2) % $organizations->count()];
            $memberOrganization = $organizations[($index * 2 + 1) % $organizations->count()];
            $leader->forceFill(['organization_id' => $leaderOrganization->id, 'availability_status' => 'AVAILABLE'])->save();
            $member->forceFill(['organization_id' => $memberOrganization->id, 'availability_status' => 'AVAILABLE'])->save();
            $projects->push($project);
        }

        return $projects;
    }

    /** @param Collection<int, Project> $projects
     * @param  array<string, list<User>>  $users
     */
    private function seedWorkflowAndTeams($projects, array $users): void
    {
        foreach ($projects as $index => $project) {
            DB::table('project_members')->updateOrInsert(
                ['project_id' => $project->id, 'user_id' => $users['Team Leader'][$index]->id],
                ['role_in_project' => 'LEADER', 'status' => 'ACTIVE', 'joined_at' => $project->created_at, 'left_at' => null, 'created_at' => $project->created_at, 'updated_at' => $project->updated_at],
            );
            DB::table('project_members')->updateOrInsert(
                ['project_id' => $project->id, 'user_id' => $users['SIMer / Member'][$index]->id],
                ['role_in_project' => 'MEMBER', 'status' => 'ACTIVE', 'joined_at' => $project->created_at, 'left_at' => null, 'created_at' => $project->created_at, 'updated_at' => $project->updated_at],
            );

            if ($project->proposal_status === ProposalStatus::Approved) {
                app(InitializeProjectSteps::class)->handle($project);
                $this->seedStepProgress($project, (int) $index);
            }

            $this->seedProposalHistory($project, $users['Owner QCC'][$index % 15]);

            if (in_array($project->proposal_status, [ProposalStatus::Submitted, ProposalStatus::UnderReview, ProposalStatus::RevisionRequired, ProposalStatus::Rejected], true)) {
                ReviewAssignment::query()->updateOrCreate(
                    ['project_id' => $project->id, 'reviewer_id' => $users['Owner QCC'][$index % 15]->id, 'scope' => 'proposal'],
                    ['granted_by' => $users['Super Admin'][$index % 15]->id, 'status' => ReviewAssignment::ACTIVE],
                );
            }
        }
    }

    private function seedStepProgress(Project $project, int $projectIndex): void
    {
        $approvedSteps = [2, 3, 4][$projectIndex - 12] ?? 2;
        $now = now()->subDays(25 - $projectIndex);

        foreach ($project->steps()->get() as $step) {
            $isApproved = $step->step_number <= $approvedSteps;
            $isInProgress = ! $isApproved && $step->step_number === $approvedSteps + 1;
            $step->forceFill([
                'activity_description' => $step->definition()->label().': pengumpulan dan pembahasan data bersama anggota tim.',
                'plan_text' => 'Validasi data, dokumentasikan hasil, dan bahas tindak lanjut pada pertemuan tim.',
                'actual_text' => $isApproved ? 'Data dan hasil langkah telah dibahas bersama tim.' : null,
                'target_date' => now()->addDays(14 + $step->step_number)->toDateString(),
                'status' => $isApproved ? StepStatus::Approved : ($isInProgress ? StepStatus::InProgress : StepStatus::NotStarted),
                'progress_pct' => $isApproved ? 100 : ($isInProgress ? 35 : 0),
                'approved_at' => $isApproved ? $now : null,
            ])->save();
        }
    }

    private function seedProposalHistory(Project $project, User $reviewer): void
    {
        $decisions = match ($project->proposal_status) {
            ProposalStatus::UnderReview => [['REVIEW_STARTED', ProposalStatus::Submitted, ProposalStatus::UnderReview, null]],
            ProposalStatus::RevisionRequired => [
                ['REVIEW_STARTED', ProposalStatus::Submitted, ProposalStatus::UnderReview, null],
                ['REQUEST_REVISION', ProposalStatus::UnderReview, ProposalStatus::RevisionRequired, 'Mohon lengkapi data baseline dan jelaskan sumber pengukuran.'],
            ],
            ProposalStatus::Rejected => [
                ['REVIEW_STARTED', ProposalStatus::Submitted, ProposalStatus::UnderReview, null],
                ['REJECT', ProposalStatus::UnderReview, ProposalStatus::Rejected, 'Ruang lingkup perlu diselaraskan dengan prioritas batch berikutnya.'],
            ],
            ProposalStatus::Approved => [
                ['REVIEW_STARTED', ProposalStatus::Submitted, ProposalStatus::UnderReview, null],
                ['APPROVE', ProposalStatus::UnderReview, ProposalStatus::Approved, null],
            ],
            default => [],
        };

        foreach ($decisions as $offset => [$decision, $previous, $next, $reason]) {
            $decidedAt = $project->submitted_at?->copy()->addDays($offset + 1) ?? now();
            DB::table('review_decisions')->updateOrInsert(
                ['project_id' => $project->id, 'decision' => $decision],
                [
                    'actor_id' => $reviewer->id,
                    'previous_status' => $previous->value,
                    'new_status' => $next->value,
                    'reason' => $reason,
                    'decided_at' => $decidedAt,
                    'created_at' => $decidedAt,
                    'updated_at' => $decidedAt,
                ],
            );
        }
    }

    /** @param Collection<int, Project> $approvedProjects
     * @param  Collection<int, Project>  $allProjects
     * @param  array<string, list<User>>  $users
     */
    private function seedSupportingData($approvedProjects, $allProjects, array $users): void
    {
        $mentors = $users['Mentor'];
        $owner = $users['Owner QCC'][0];
        $actionIndex = 0;
        $riskRows = [];

        foreach ($approvedProjects as $approvedIndex => $project) {
            $projectIndex = $allProjects->search(fn (Project $item): bool => $item->is($project));

            if (! is_int($projectIndex)) {
                throw new \RuntimeException('Proyek demo yang disetujui tidak ditemukan di daftar proyek.');
            }

            $leader = $users['Team Leader'][$projectIndex] ?? throw new \RuntimeException('Team Leader demo tidak ditemukan.');
            $member = $users['SIMer / Member'][$projectIndex] ?? throw new \RuntimeException('Member demo tidak ditemukan.');
            $mentor = $mentors[$approvedIndex];
            $assignment = ProjectMentor::query()->updateOrCreate(
                ['project_id' => $project->id, 'mentor_id' => $mentor->id],
                ['assigned_by' => $owner->id, 'status' => 'ACTIVE', 'assigned_at' => $project->approved_at ?? now()],
            );

            $this->seedReports($project, $assignment, $leader, $member, $mentor, $owner, $approvedIndex);

            for ($perProjectIndex = 1; $perProjectIndex <= 5; $perProjectIndex++) {
                $riskIndex = $actionIndex++;
                $riskTitle = $riskIndex % 3 === 0
                    ? 'Keterlambatan data pendukung tahap '.$perProjectIndex
                    : ($riskIndex % 3 === 1 ? 'Kendala koordinasi lintas unit '.$perProjectIndex : 'Isu validasi hasil uji '.$perProjectIndex);
                $riskStatus = [RiskStatus::Open, RiskStatus::InProgress, RiskStatus::Resolved, RiskStatus::Closed, RiskStatus::Open][$perProjectIndex - 1];
                $risk = ProjectRisk::query()->updateOrCreate(
                    ['project_id' => $project->id, 'title' => '[DEMO] '.$riskTitle],
                    [
                        'type' => $riskIndex % 3 === 1 ? RiskType::Issue : RiskType::Risk,
                        'description' => 'Data, jadwal, atau koordinasi dapat menghambat penyelesaian aktivitas proyek.',
                        'impact' => 'Target waktu dan validitas hasil dapat terdampak bila tidak ditindaklanjuti.',
                        'likelihood' => [RiskLevel::Low, RiskLevel::Medium, RiskLevel::High][$riskIndex % 3],
                        'priority' => [RiskLevel::Medium, RiskLevel::High, RiskLevel::Critical][$riskIndex % 3],
                        'mitigation' => 'Tetapkan PIC, konfirmasi sumber data, dan tinjau progres pada rapat tim berikutnya.',
                        'pic_id' => $perProjectIndex % 2 === 0 ? $member->id : $leader->id,
                        'due_date' => now()->addDays(7 + $riskIndex)->toDateString(),
                        'status' => $riskStatus,
                        'resolved_at' => $riskStatus->isFinished() ? now()->subDays(2) : null,
                        'created_by' => $leader->id,
                    ],
                );
                $riskRows[] = $risk;

                $actionStatus = [ActionStatus::Open, ActionStatus::InProgress, ActionStatus::Blocked, ActionStatus::Completed, ActionStatus::Open][$perProjectIndex - 1];
                ActionItem::query()->updateOrCreate(
                    ['project_id' => $project->id, 'title' => '[DEMO] Tindak lanjut '.$riskTitle],
                    [
                        'source_type' => 'RISK',
                        'source_id' => $risk->id,
                        'description' => 'Koordinasikan tindak lanjut dengan PIC dan simpan hasil pembaruan.',
                        'pic_id' => $perProjectIndex % 2 === 0 ? $leader->id : $member->id,
                        'due_date' => now()->addDays(5 + $riskIndex)->toDateString(),
                        'status' => $actionStatus,
                        'completed_at' => $actionStatus === ActionStatus::Completed ? now()->subDay() : null,
                        'created_by' => $leader->id,
                    ],
                );
            }
        }

        $this->seedEvidence($approvedProjects, $allProjects, $users);
    }

    private function seedReports(Project $project, ProjectMentor $assignment, User $leader, User $member, User $mentor, User $owner, int $approvedIndex): void
    {
        $biweeklyStatuses = [ReportStatus::Approved, ReportStatus::Approved, ReportStatus::RevisionRequired, ReportStatus::Submitted, ReportStatus::Draft];
        $mentoringStatuses = [ReportStatus::Acknowledged, ReportStatus::Acknowledged, ReportStatus::Submitted, ReportStatus::Submitted, ReportStatus::Draft];

        for ($period = 1; $period <= 5; $period++) {
            $startsOn = now()->subDays(14 * $period)->startOfDay();
            $endsOn = $startsOn->copy()->addDays(13);
            $status = $biweeklyStatuses[$period - 1];
            $report = BiweeklyReport::query()->updateOrCreate(
                ['project_id' => $project->id, 'period_no' => $period],
                [
                    'starts_on' => $startsOn->toDateString(),
                    'ends_on' => $endsOn->toDateString(),
                    'meeting_date' => $endsOn->toDateString(),
                    'agenda' => 'Tinjauan progres dan hambatan periode '.$period,
                    'summary' => 'Tim meninjau hasil pekerjaan, membandingkan rencana dengan realisasi, dan menyepakati tindak lanjut.',
                    'planned' => 'Validasi data, uji perbaikan, dan dokumentasi kegiatan.',
                    'actual' => $period === 1 ? 'Pengumpulan baseline dan pemetaan proses selesai.' : 'Uji coba berjalan; hasil dicatat pada lembar pemantauan tim.',
                    'issues' => $period === 3 ? 'Data pembanding dari unit terkait masih menunggu konfirmasi.' : null,
                    'decisions' => 'PIC mengirim pembaruan data sebelum pertemuan berikutnya.',
                    'status' => $status,
                    'created_by' => $leader->id,
                ],
            );
            $report->forceFill([
                'submitted_by' => $status === ReportStatus::Draft ? null : $leader->id,
                'submitted_at' => $status === ReportStatus::Draft ? null : $endsOn,
                'reviewed_by' => in_array($status, [ReportStatus::Approved, ReportStatus::RevisionRequired], true) ? $owner->id : null,
                'reviewed_at' => in_array($status, [ReportStatus::Approved, ReportStatus::RevisionRequired], true) ? $endsOn->copy()->addDay() : null,
                'review_note' => $status === ReportStatus::RevisionRequired ? 'Lampirkan hasil pembandingan terhadap baseline.' : null,
            ])->save();
            DB::table('biweekly_attendees')->updateOrInsert(
                ['biweekly_report_id' => $report->id, 'user_id' => $leader->id],
                [],
            );
            DB::table('biweekly_attendees')->updateOrInsert(
                ['biweekly_report_id' => $report->id, 'user_id' => $member->id],
                [],
            );

            $mentorStatus = $mentoringStatuses[$period - 1];
            $sessionAt = $endsOn->copy()->setTime(10, 0);
            $mentoringReport = DB::table('mentoring_reports')->where('project_mentor_id', $assignment->id)->where('topic', 'Sesi pendampingan '.$period)->first();
            $reportValues = [
                'project_id' => $project->id,
                'project_mentor_id' => $assignment->id,
                'session_at' => $sessionAt,
                'topic' => 'Sesi pendampingan '.$period,
                'findings' => 'Tim memiliki pemahaman proses yang baik dan perlu menjaga konsistensi pencatatan data.',
                'suggestions' => 'Gunakan sumber data yang sama untuk baseline dan pengukuran hasil.',
                'next_steps' => 'Tinjau bukti pelaksanaan dan tindak lanjut pada sesi berikutnya.',
                'status' => $mentorStatus->value,
                'submitted_by' => $mentorStatus === ReportStatus::Draft ? null : $mentor->id,
                'submitted_at' => $mentorStatus === ReportStatus::Draft ? null : $sessionAt,
                'acknowledged_by' => $mentorStatus === ReportStatus::Acknowledged ? $leader->id : null,
                'acknowledged_at' => $mentorStatus === ReportStatus::Acknowledged ? $sessionAt->copy()->addHours(2) : null,
                'team_response' => $mentorStatus === ReportStatus::Acknowledged ? 'Tim menindaklanjuti masukan dan memperbarui catatan proyek.' : null,
                'created_by' => $mentor->id,
                'created_at' => $sessionAt,
                'updated_at' => $sessionAt,
            ];

            if ($mentoringReport === null) {
                DB::table('mentoring_reports')->insert($reportValues);
            } else {
                DB::table('mentoring_reports')->where('id', $mentoringReport->id)->update($reportValues);
            }
        }
    }

    /** @param Collection<int, Project> $approvedProjects
     * @param  Collection<int, Project>  $allProjects
     * @param  array<string, list<User>>  $users
     */
    private function seedEvidence($approvedProjects, $allProjects, array $users): void
    {
        $disk = (string) config('qcc_evidence.disk');
        $image = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aS5kAAAAASUVORK5CYII=', true);

        if ($image === false) {
            throw new \RuntimeException('Demo evidence image fixture is invalid.');
        }

        foreach ($approvedProjects as $approvedIndex => $project) {
            $projectIndex = (int) $allProjects->search(fn (Project $item): bool => $item->is($project));
            $uploader = $users['Team Leader'][$projectIndex];

            for ($itemNumber = 1; $itemNumber <= 5; $itemNumber++) {
                $storageKey = sprintf('demo/projects/%d/evidence-%02d.png', $project->id, $itemNumber);
                $existing = ProjectEvidence::query()->where('storage_disk', $disk)->where('storage_key', $storageKey)->first();

                if ($existing !== null) {
                    continue;
                }

                Storage::disk($disk)->put($storageKey, $image);
                $inspection = app(EvidenceFileInspector::class)->inspectPath(Storage::disk($disk)->path($storageKey), 'png');

                if (! $inspection['valid']) {
                    Storage::disk($disk)->delete($storageKey);
                    throw new \RuntimeException('Demo evidence image fixture failed file inspection.');
                }

                $evidence = ProjectEvidence::query()->create([
                    'project_id' => $project->id,
                    'uploaded_by' => $uploader->id,
                    'title' => sprintf('[DEMO] Bukti kegiatan %02d', ($approvedIndex * 5) + $itemNumber),
                    'category' => 'QCC_STEP',
                    'description' => 'Berkas uji lokal untuk alur unggah dan pemeriksaan evidence.',
                    'source_type' => 'FILE',
                    'storage_disk' => $disk,
                    'storage_key' => $storageKey,
                    'original_name' => sprintf('bukti-kegiatan-%02d.png', ($approvedIndex * 5) + $itemNumber),
                    'file_extension' => 'png',
                    'mime_type' => 'image/png',
                    'size_bytes' => strlen($image),
                    'checksum_sha256' => hash('sha256', $image),
                    'version_group' => $this->demoUuid('evidence-'.$project->id.'-'.$itemNumber),
                    'version_number' => 1,
                    'scan_status' => 'PENDING',
                    'processing_status' => 'PENDING',
                    'extraction_status' => 'PENDING',
                    'metadata_json' => $inspection['metadata'],
                ]);
                $step = $project->steps()->where('step_number', min(8, $itemNumber + 1))->firstOrFail();
                $evidence->links()->create([
                    'target_type' => ProjectStep::class,
                    'target_id' => $step->id,
                    'created_by' => $uploader->id,
                ]);
            }
        }
    }

    private function demoUuid(string $key): string
    {
        $hash = sha1('portal-qcc-demo-'.$key);

        return sprintf('%s-%s-5%s-8%s-%s', substr($hash, 0, 8), substr($hash, 8, 4), substr($hash, 13, 3), substr($hash, 17, 3), substr($hash, 20, 12));
    }
}
