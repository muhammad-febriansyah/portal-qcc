<?php

use App\Enums\ProposalStatus;
use App\Enums\QccStep;
use App\Enums\ThemeCategory;
use App\Models\Competency;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ProjectMentor;
use App\Models\QccBatch;
use App\Models\ReviewDecision;
use App\Models\User;
use App\Services\ProposalPresentationBuilder;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Spatie\Permission\Models\Permission;

/**
 * The slides of a deck in presentation order, numbered from 1.
 *
 * @return array<int, string> slide XML
 */
function presentationSlides(string $path): array
{
    $archive = new ZipArchive;
    expect($archive->open($path))->toBeTrue();
    preg_match_all('#<p:sldId [^>]*r:id="([^"]+)"#', (string) $archive->getFromName('ppt/presentation.xml'), $ids);
    $relationships = (string) $archive->getFromName('ppt/_rels/presentation.xml.rels');
    $slides = [];

    foreach ($ids[1] as $index => $id) {
        preg_match('#Id="'.preg_quote($id, '#').'"[^>]*Target="([^"]+)"#', $relationships, $target);
        $slides[$index + 1] = (string) $archive->getFromName('ppt/'.$target[1]);
    }

    $archive->close();

    return $slides;
}

/**
 * The first slide whose text contains $needle.
 *
 * @param  array<int, string>  $slides
 */
function slideWith(array $slides, string $needle): string
{
    foreach ($slides as $slide) {
        if (str_contains(slideText($slide), $needle)) {
            return $slide;
        }
    }

    throw new RuntimeException("No slide contains {$needle}.");
}

/**
 * The words written on a slide.
 */
function slideText(string $xml): string
{
    preg_match_all('#<a:t(?: [^>]*)?>([^<]*)</a:t>#', $xml, $runs);

    return html_entity_decode(implode(' ', $runs[1]), ENT_QUOTES | ENT_XML1);
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
    $content = slideWith($slides, 'JUDUL PROYEK');
    $schedule = slideWith($slides, 'Jadwal Proyek');
    $approval = slideWith($slides, 'Persetujuan Proposal');
    // Body rows of the gantt table; the two header rows come first.
    $bodyRows = implode('', array_slice(explode('<a:tr ', $schedule), 3));

    expect(implode('', $slides))->not->toContain('{{')
        ->and($slides[1])->toContain('Digitalisasi verifikasi &amp; arsip', 'Ayu Pengusul  |  SIM ID 2551  |  DIV IT', 'Kategori tema: Productivity', 'SIMGROUP  |  QCC BATCH 4', '2026–2027')
        ->and($content)->toContain('masih manual</a:t>', ' b="1"', '<a:buChar char="•"/>', 'Banyak salah input')
        ->and(slideText(slideWith($slides, '3 HAL YANG AKAN DIPELAJARI')))->toContain('1. Analisis data', '3. SOP')
        ->and($content)->not->toContain('**')
        // Flat white cards (no border, no shadow) and the QCC logo of the Batch 4 template.
        ->and($content)->toContain('<a:solidFill><a:srgbClr val="FFFFFF"/></a:solidFill><a:ln><a:noFill/></a:ln>', '<a:blip r:embed=')
        ->and(implode('', $slides))->not->toMatch('/effectRef idx="[1-9]"/')
        ->and(implode('', $slides))->not->toContain('Aptos')
        ->and(slideText($schedule))->toContain('Jan-27', '4 Jan 2027', '15 Jan 2027', 'Tema terpilih')
        // Step 1 runs through weeks 1-3 of January, step 2 through weeks 3-4.
        ->and(substr_count($bodyRows, '<a:srgbClr val="'.ProposalPresentationBuilder::PRIMARY.'"/></a:solidFill></a:tcPr>'))->toBe(5)
        ->and(slideText($approval))->toContain('MENTOR', 'Citra Mentor', 'Dodi Mentor', 'DIV IT HEAD', 'Disetujui 7 Nov 2026')
        ->and(slideText($approval))->not->toContain('Budi Owner')
        ->and(slideText($approval))->not->toContain('Owner QCC')
        ->and(slideText($approval))->not->toContain('LOLOS SELEKSI')
        ->and(slideText($approval))->not->toContain('SUDAH DIAJUKAN');
});

test('long text continues on the next card instead of being cut', function () {
    $proposer = presentationViewer();
    $project = Project::factory()->status(ProposalStatus::Submitted)->create([
        'proposer_id' => $proposer->id,
        'background' => str_repeat('Data proses verifikasi dokumen. ', 30),
        'scope' => str_repeat('Ruang lingkup yang sangat panjang sekali. ', 60),
    ]);

    $response = $this->actingAs($proposer)->get(route('proposals.presentation', $project))->assertOk();
    $slides = presentationSlides($response->baseResponse->getFile()->getPathname());

    $text = implode(' ', array_map(slideText(...), $slides));
    $approval = slideText(slideWith($slides, 'Persetujuan Proposal'));

    // Every word arrives, nothing ends in an ellipsis, and the overflow is labelled.
    expect(substr_count($text, 'verifikasi'))->toBe(30)
        ->and(substr_count($text, 'sekali'))->toBe(60)
        ->and($text)->not->toContain('…')
        ->and($text)->toContain('RUANG LINGKUP PROYEK (LANJUTAN)')
        ->and(count($slides))->toBeGreaterThan(5)
        ->and($approval)->toContain('MENTOR', 'Belum ditentukan', 'Belum diajukan')
        ->and($approval)->not->toContain('DRAF')
        ->and($approval)->not->toContain('Owner QCC');
});

test('only people who may open the proposal can download it', function () {
    $project = Project::factory()->create();
    $stranger = presentationViewer();

    $this->actingAs($stranger)->get(route('proposals.presentation', $project))->assertForbidden();
});

test('the mentor card falls back to the organization mentors before anyone is assigned', function () {
    $proposer = presentationViewer();
    $proposer->organization->mentors()->attach(User::factory()->create(['name' => 'Eka Mentor']));
    $project = Project::factory()->status(ProposalStatus::Submitted)->create(['proposer_id' => $proposer->id]);

    $response = $this->actingAs($proposer)->get(route('proposals.presentation', $project))->assertOk();
    $slides = presentationSlides($response->baseResponse->getFile()->getPathname());

    $approval = slideText(slideWith($slides, 'Persetujuan Proposal'));

    expect($approval)->toContain('Eka Mentor', 'DIV IT HEAD')
        ->and($approval)->not->toContain('Disetujui');
});

/**
 * A draft with every mandatory part of a proposal filled in.
 */
function completeDraft(User $proposer): Project
{
    $project = Project::factory()->status(ProposalStatus::Draft)->create([
        'proposer_id' => $proposer->id,
        'title' => 'Digitalisasi verifikasi',
        'theme' => 'Proses verifikasi',
        'theme_category' => ThemeCategory::Productivity,
        'background' => 'Latar belakang',
        'current_situation' => 'Baseline 10 jam',
        'improvement_plan' => 'Checklist digital',
        'target' => 'Target 5 jam',
        'scope' => 'Unit verifikasi',
        'learning_goals_json' => ['Analisis data', 'Lean', 'SOP'],
        'step_timeline_json' => array_map(fn (QccStep $step): array => [
            'step_number' => $step->value,
            'start_date' => now()->addWeeks($step->value - 1)->toDateString(),
            'target_date' => now()->addWeeks($step->value)->toDateString(),
        ], QccStep::cases()),
    ]);
    DB::table('project_requirements')->insert([
        'project_id' => $project->id,
        'competency_id' => Competency::factory()->create()->id,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return $project;
}

test('a draft exports once every mandatory part is filled, before it is submitted', function () {
    $proposer = presentationViewer();
    $project = completeDraft($proposer);

    $this->actingAs($proposer)->get(route('proposals.presentation', $project))->assertOk();

    $this->get(route('proposals.show', $project))
        ->assertInertia(fn (AssertableInertia $page) => $page->where('proposal.export_missing', []));

    expect($project->fresh()->proposal_status)->toBe(ProposalStatus::Draft);
});

test('a draft with mandatory parts missing cannot be exported and says what is missing', function () {
    $proposer = presentationViewer();
    $proposer->givePermissionTo(Permission::findOrCreate('projects.update', 'web'));
    $project = completeDraft($proposer);
    $project->forceFill(['target' => null, 'learning_goals_json' => ['Analisis data']])->save();

    $this->actingAs($proposer)->get(route('proposals.presentation', $project))->assertStatus(422);

    $this->get(route('proposals.show', $project))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('proposal.export_missing', ['Target/tujuan', '3 hal yang akan dipelajari']));

    $this->get(route('proposals.edit', $project))
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('project.export_missing', ['Target/tujuan', '3 hal yang akan dipelajari']));
});
