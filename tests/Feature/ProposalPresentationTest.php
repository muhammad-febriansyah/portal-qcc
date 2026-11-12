<?php

use App\Enums\ProposalStatus;
use App\Enums\ThemeCategory;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ProjectMentor;
use App\Models\QccBatch;
use App\Models\ReviewDecision;
use App\Models\User;
use Spatie\Permission\Models\Permission;

/**
 * @return array<string, string> slide XML keyed by slide number
 */
function presentationSlides(string $path): array
{
    $archive = new ZipArchive;
    expect($archive->open($path))->toBeTrue();
    $slides = [];

    for ($number = 1; $number <= 7; $number++) {
        $slides[$number] = (string) $archive->getFromName("ppt/slides/slide{$number}.xml");
    }

    $archive->close();

    return $slides;
}

function presentationViewer(): User
{
    $user = User::factory()->create(['name' => 'Ayu Pengusul', 'sim_id' => '2551', 'position' => 'Staf Operasional', 'organization_id' => Organization::factory()->create(['name' => 'DIV IT'])->id]);
    $user->givePermissionTo(Permission::findOrCreate('projects.view', 'web'));

    return $user;
}

test('a proposal downloads as a filled presentation', function () {
    $proposer = presentationViewer();
    $owner = User::factory()->create(['name' => 'Budi Owner', 'position' => 'Koordinator QCC']);
    $project = Project::factory()->status(ProposalStatus::Approved)->create([
        'batch_id' => QccBatch::factory()->create(['name' => 'QCC Batch 4', 'starts_on' => '2026-11-01', 'ends_on' => '2027-07-31'])->id,
        'proposer_id' => $proposer->id,
        'title' => 'Digitalisasi verifikasi & arsip',
        'theme' => 'Proses verifikasi dokumen',
        'theme_category' => ThemeCategory::Productivity,
        'background' => "Verifikasi **masih manual**.\n- Banyak salah input\n- Lambat",
        'improvement_plan' => 'Checklist digital',
        'learning_goals_json' => ['Analisis data', 'Lean', 'SOP'],
        'step_timeline_json' => [
            ['step_number' => 1, 'start_date' => '2027-01-04', 'target_date' => '2027-01-15', 'deliverable' => 'Tema terpilih'],
            ['step_number' => 2, 'start_date' => '2027-01-18', 'target_date' => '2027-01-29', 'deliverable' => 'Target SMART'],
        ],
        'submitted_at' => '2026-11-05 09:00:00',
    ]);
    $mentor = User::factory()->create(['name' => 'Citra Mentor']);
    $secondMentor = User::factory()->create(['name' => 'Dodi Mentor']);
    foreach ([$mentor, $secondMentor] as $assigned) {
        ProjectMentor::query()->create(['project_id' => $project->id, 'mentor_id' => $assigned->id, 'status' => 'ACTIVE', 'assigned_at' => now()]);
    }
    ReviewDecision::query()->create([
        'project_id' => $project->id,
        'actor_id' => $owner->id,
        'decision' => 'APPROVE',
        'previous_status' => ProposalStatus::UnderReview->value,
        'new_status' => ProposalStatus::Approved->value,
        'decided_at' => '2026-11-07 10:00:00',
    ]);

    $response = $this->actingAs($proposer)->get(route('proposals.presentation', $project))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/vnd.openxmlformats-officedocument.presentationml.presentation')
        ->assertDownload(sprintf('Proposal PRP-%04d - Digitalisasi verifikasi arsip.pptx', $project->id));

    $slides = presentationSlides($response->baseResponse->getFile()->getPathname());

    expect(implode('', $slides))->not->toContain('{{')
        ->and($slides[1])->toContain('Digitalisasi verifikasi &amp; arsip', 'Ayu Pengusul  |  SIM ID 2551 | DIV IT', 'Kategori tema: Productivity', 'SIMGROUP  |  QCC BATCH 4', '2026–2027')
        ->and($slides[2])->toContain('masih manual</a:t>', ' b="1"', '<a:buChar char="•"/>', 'Banyak salah input')
        ->and($slides[2])->not->toContain('**')
        ->and($slides[4])->toContain('1. Analisis data', '3. SOP')
        ->and($slides[5])->toContain('Jan-27', '4 Jan 2027', '15 Jan 2027', 'Tema terpilih', '-')
        // Step 1 runs through weeks 1-3 of January, step 2 through weeks 3-4.
        ->and(substr_count($slides[5], '<a:srgbClr val="1F4E79"/></a:solidFill></a:tcPr>'))->toBe(5)
        ->and($slides[6])->toContain('MENTOR', 'Citra Mentor', 'Dodi Mentor', 'Mentor DIV IT', 'Disetujui 7 Nov 2026')
        ->and($slides[6])->not->toContain('Budi Owner')
        ->and($slides[6])->not->toContain('Owner QCC')
        ->and($slides[6])->not->toContain('LOLOS SELEKSI')
        ->and($slides[6])->not->toContain('SUDAH DIAJUKAN');
});

test('a long text gets a smaller font and is cut to fit its card', function () {
    $proposer = presentationViewer();
    $project = Project::factory()->create([
        'proposer_id' => $proposer->id,
        'background' => str_repeat('Data proses verifikasi dokumen. ', 30),
        'scope' => str_repeat('Ruang lingkup yang sangat panjang sekali. ', 60),
    ]);

    $response = $this->actingAs($proposer)->get(route('proposals.presentation', $project))->assertOk();
    $slides = presentationSlides($response->baseResponse->getFile()->getPathname());

    expect($slides[2])->toMatch('/sz="1[0-3]00"[^>]*>(?:(?!<\/a:rPr>).)*<\/a:rPr><a:t xml:space="preserve">Data proses/s')
        ->and($slides[3])->toContain('…')
        ->and($slides[6])->toContain('MENTOR', 'Belum ditentukan', 'Belum diajukan')
        ->and($slides[6])->not->toContain('DRAF')
        ->and($slides[6])->not->toContain('Owner QCC');
});

test('only people who may open the proposal can download it', function () {
    $project = Project::factory()->create();
    $stranger = presentationViewer();

    $this->actingAs($stranger)->get(route('proposals.presentation', $project))->assertForbidden();
});

test('the mentor card falls back to the organization mentors before anyone is assigned', function () {
    $proposer = presentationViewer();
    $proposer->organization->mentors()->attach(User::factory()->create(['name' => 'Eka Mentor']));
    $project = Project::factory()->status(ProposalStatus::Draft)->create(['proposer_id' => $proposer->id]);

    $response = $this->actingAs($proposer)->get(route('proposals.presentation', $project))->assertOk();
    $slides = presentationSlides($response->baseResponse->getFile()->getPathname());

    expect($slides[6])->toContain('Eka Mentor', 'Mentor DIV IT')
        ->and($slides[6])->not->toContain('Disetujui');
});
