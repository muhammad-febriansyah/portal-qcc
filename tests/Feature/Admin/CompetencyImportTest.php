<?php

use App\Models\AuditLog;
use App\Models\Competency;
use App\Models\User;
use App\Services\Spreadsheet\XlsxReader;
use App\Services\Spreadsheet\XlsxWriter;
use Illuminate\Http\UploadedFile;
use Spatie\Permission\Models\Permission;

function competencyImporter(): User
{
    $user = User::factory()->create();
    $user->givePermissionTo(Permission::findOrCreate('competencies.manage', 'web'));

    return $user;
}

/**
 * @param  list<list<string|int|null>>  $rows
 */
function competencyWorkbook(array $rows): UploadedFile
{
    $path = tempnam(sys_get_temp_dir(), 'competency-test-');
    (new XlsxWriter)->write($path, [['name' => 'Kompetensi', 'rows' => $rows]]);
    $contents = file_get_contents($path);
    unlink($path);

    return UploadedFile::fake()->createWithContent('kompetensi.xlsx', (string) $contents);
}

test('an authorized admin imports and upserts skills from Excel', function () {
    Competency::factory()->create([
        'code' => 'H01',
        'name' => 'Old test-only name',
        'description' => 'Old test-only definition',
    ]);

    $rows = [['Kode', 'Nama Kompetensi', 'Definisi']];

    foreach (range(1, 48) as $number) {
        $rows[] = [sprintf('H%02d', $number), "Synthetic hard skill {$number}", "Synthetic definition {$number}"];
    }

    foreach (range(1, 18) as $number) {
        $rows[] = [sprintf('S%02d', $number), "Synthetic soft skill {$number}", "Synthetic definition {$number}"];
    }

    $this->actingAs(competencyImporter())
        ->from(route('admin.competencies.index'))
        ->post(route('admin.competencies.import'), ['file' => competencyWorkbook($rows)])
        ->assertRedirect(route('admin.competencies.index'))
        ->assertSessionHasNoErrors();

    expect(Competency::query()->count())->toBe(66)
        ->and(Competency::query()->where('code', 'H01')->value('name'))->toBe('Synthetic hard skill 1')
        ->and(Competency::query()->whereNotNull('group_id')->count())->toBe(0);

    $audit = AuditLog::query()->where('event', 'competency.imported')->latest('id')->firstOrFail();
    expect($audit->after_json)->toMatchArray(['created' => 65, 'updated' => 1, 'total' => 66]);

    $this->get(route('admin.competencies.index'))
        ->assertInertia(fn ($page) => $page
            ->component('admin/competencies/index')
            ->where('competencies.total', 66)
            ->missing('groups'));
});

test('empty rows are skipped and lower case codes are accepted', function () {
    $rows = [
        ['Kode', 'Nama Kompetensi', 'Definisi'],
        ['H01', 'Filled skill', 'Filled definition'],
        [null, null, null],
        ['s03', 'Lowercase input', null],
    ];

    $this->actingAs(competencyImporter())
        ->post(route('admin.competencies.import'), ['file' => competencyWorkbook($rows)])
        ->assertSessionHasNoErrors();

    expect(Competency::query()->orderBy('code')->pluck('name', 'code')->all())
        ->toBe(['H01' => 'Filled skill', 'S03' => 'Lowercase input']);
});

test('the dictionary headers and English headers are accepted as well', function () {
    $this->actingAs(competencyImporter());

    $this->post(route('admin.competencies.import'), ['file' => competencyWorkbook([
        ['code', 'name', 'description'],
        ['H01', 'English header skill', 'Definition'],
    ])])->assertSessionHasNoErrors();

    $this->post(route('admin.competencies.import'), ['file' => competencyWorkbook([
        ['Skill ID', 'Kategori', 'Nama Skill', 'Deskripsi Sederhana', 'Contoh Aktivitas yang Mudah Dikenali'],
        ['S01', 'Soft Skill', 'Effective Communication', 'Menyampaikan informasi secara jelas.', 'Menjelaskan pekerjaan.'],
    ])])->assertSessionHasNoErrors();

    expect(Competency::query()->orderBy('code')->pluck('description', 'code')->all())
        ->toBe(['H01' => 'Definition', 'S01' => 'Menyampaikan informasi secara jelas.']);
});

test('invalid rows show Excel row errors and roll back the complete import', function () {
    $rows = [
        ['Kode', 'Nama Kompetensi', 'Definisi'],
        ['H01', 'Test-only skill', 'Test-only definition'],
        ['H01', 'Duplicate row', 'Duplicate definition'],
        ['C001', 'Old code format', 'Test-only definition'],
        ['S02', null, 'Test-only definition'],
    ];

    $this->actingAs(competencyImporter())
        ->from(route('admin.competencies.index'))
        ->post(route('admin.competencies.import'), ['file' => competencyWorkbook($rows)])
        ->assertRedirect(route('admin.competencies.index'))
        ->assertSessionHasErrors(['row_3', 'row_4', 'row_5']);

    expect(Competency::query()->count())->toBe(0)
        ->and(AuditLog::query()->where('event', 'competency.imported')->count())->toBe(0);
});

test('a workbook with other headers is rejected with a pointer to the template', function () {
    $rows = [['Nomor', 'Judul'], ['1', 'Something']];

    $this->actingAs(competencyImporter())
        ->post(route('admin.competencies.import'), ['file' => competencyWorkbook($rows)])
        ->assertSessionHasErrors(['file' => 'Header tidak sesuai template. Kolom wajib: Kode, Nama Kompetensi, dan Definisi (satu kali). Unduh ulang template jika perlu.']);

    expect(Competency::query()->count())->toBe(0);
});

test('a CSV upload is rejected because only Excel files are accepted', function () {
    $csv = "code,name,description\nH01,Test-only competency,Test-only definition\n";

    $this->actingAs(competencyImporter())
        ->post(route('admin.competencies.import'), [
            'file' => UploadedFile::fake()->createWithContent('competencies.csv', $csv),
        ])
        ->assertSessionHasErrors(['file' => 'File harus berformat Excel (.xlsx).']);

    expect(Competency::query()->count())->toBe(0);
});

test('a file renamed to xlsx that is not a workbook is rejected', function () {

    $this->actingAs(competencyImporter())
        ->post(route('admin.competencies.import'), [
            'file' => UploadedFile::fake()->createWithContent('kompetensi.xlsx', 'this is not a zip archive'),
        ])
        ->assertSessionHasErrors('file');

    expect(Competency::query()->count())->toBe(0);
});

test('users without competencies manage permission are forbidden from importing', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('admin.competencies.import'))
        ->assertForbidden();

    expect(Competency::query()->count())->toBe(0);
});

test('the template lists every skill in the master without a group column', function () {
    Competency::factory()->create(['code' => 'S01', 'name' => 'Soft skill', 'description' => 'Soft definition']);
    Competency::factory()->create(['code' => 'H02', 'name' => 'Hard skill', 'description' => 'Hard definition']);

    $response = $this->actingAs(competencyImporter())
        ->get(route('admin.competencies.template'))
        ->assertOk()
        ->assertDownload('template-master-kompetensi.xlsx');

    $path = tempnam(sys_get_temp_dir(), 'template-test-');
    file_put_contents($path, $response->streamedContent() ?: $response->baseResponse->getFile()->getContent());
    $rows = (new XlsxReader)->firstSheetRows($path);
    unlink($path);

    expect($rows)->toHaveCount(3)
        ->and($rows[0]['cells'])->toBe(['Kode', 'Nama Kompetensi', 'Definisi'])
        ->and($rows[1]['cells'])->toBe(['H02', 'Hard skill', 'Hard definition'])
        ->and($rows[2]['cells'])->toBe(['S01', 'Soft skill', 'Soft definition']);
});

test('a downloaded template that is filled in can be imported again', function () {
    Competency::factory()->create(['code' => 'H01', 'name' => 'Before', 'description' => 'Old definition']);
    $admin = competencyImporter();

    $response = $this->actingAs($admin)->get(route('admin.competencies.template'));
    $template = tempnam(sys_get_temp_dir(), 'template-test-');
    file_put_contents($template, $response->baseResponse->getFile()->getContent());
    $rows = array_map(fn (array $row): array => $row['cells'], (new XlsxReader)->firstSheetRows($template));
    unlink($template);

    $rows[1] = ['H01', 'Filled from template', 'Template definition'];
    $rows[] = ['S01', 'Added at the bottom', 'New definition'];

    $this->actingAs($admin)
        ->post(route('admin.competencies.import'), ['file' => competencyWorkbook($rows)])
        ->assertSessionHasNoErrors();

    expect(Competency::query()->orderBy('code')->pluck('name', 'code')->all())
        ->toBe(['H01' => 'Filled from template', 'S01' => 'Added at the bottom']);
});

test('users without competencies manage permission cannot download the template', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.competencies.template'))
        ->assertForbidden();
});

test('the master list shows the active skills first and the retired ones on request', function () {
    Competency::factory()->create(['code' => 'H01', 'is_active' => true]);
    Competency::factory()->create(['code' => 'C001', 'is_active' => false]);
    $admin = competencyImporter();
    $admin->givePermissionTo(Permission::findOrCreate('competencies.view', 'web'));

    $this->actingAs($admin)
        ->get(route('admin.competencies.index'))
        ->assertInertia(fn ($page) => $page
            ->where('filters.status', 'active')
            ->has('competencies.data', 1)
            ->where('competencies.data.0.code', 'H01'));

    $this->get(route('admin.competencies.index', ['status' => 'all']))
        ->assertInertia(fn ($page) => $page->has('competencies.data', 2));
});
