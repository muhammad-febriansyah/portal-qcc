<?php

use App\Models\AuditLog;
use App\Models\Competency;
use App\Models\CompetencyGroup;
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

/**
 * @return array<string, int>
 */
function competencyGroups(): array
{
    return collect(range('A', 'F'))
        ->mapWithKeys(fn (string $code): array => [$code => CompetencyGroup::factory()->create(['code' => $code])->id])
        ->all();
}

test('an authorized admin imports and upserts the official competency codes from Excel', function () {
    $groups = competencyGroups();
    Competency::factory()->create([
        'group_id' => $groups['A'],
        'code' => 'C001',
        'name' => 'Old test-only name',
        'description' => 'Old test-only definition',
    ]);

    $rows = [['Kode', 'Kelompok', 'Nama Kompetensi', 'Definisi']];

    foreach (range(1, 62) as $number) {
        $rows[] = [sprintf('C%03d', $number), chr(65 + (($number - 1) % 6)), "Synthetic test competency {$number}", "Synthetic test definition {$number}"];
    }

    $this->actingAs(competencyImporter())
        ->from(route('admin.competencies.index'))
        ->post(route('admin.competencies.import'), ['file' => competencyWorkbook($rows)])
        ->assertRedirect(route('admin.competencies.index'))
        ->assertSessionHasNoErrors();

    expect(Competency::query()->count())->toBe(62)
        ->and(Competency::query()->where('code', 'C001')->value('name'))->toBe('Synthetic test competency 1');

    $audit = AuditLog::query()->where('event', 'competency.imported')->latest('id')->firstOrFail();
    expect($audit->after_json)->toMatchArray(['created' => 61, 'updated' => 1, 'total' => 62]);

    $this->get(route('admin.competencies.index'))
        ->assertInertia(fn ($page) => $page
            ->component('admin/competencies/index')
            ->where('competencies.total', 62));
});

test('rows with only a pre-filled code are skipped and group labels are accepted', function () {
    competencyGroups();

    $rows = [
        ['Kode', 'Kelompok', 'Nama Kompetensi', 'Definisi'],
        ['C001', 'A - Core QCC & Problem Solving', 'Filled competency', 'Filled definition'],
        ['C002', null, null, null],
        ['c003', 'b', 'Lowercase input', null],
    ];

    $this->actingAs(competencyImporter())
        ->post(route('admin.competencies.import'), ['file' => competencyWorkbook($rows)])
        ->assertSessionHasNoErrors();

    expect(Competency::query()->orderBy('code')->pluck('name', 'code')->all())
        ->toBe(['C001' => 'Filled competency', 'C003' => 'Lowercase input']);
});

test('English headers are accepted as well', function () {
    competencyGroups();

    $rows = [
        ['code', 'group_code', 'name', 'description'],
        ['C001', 'A', 'English header competency', 'Definition'],
    ];

    $this->actingAs(competencyImporter())
        ->post(route('admin.competencies.import'), ['file' => competencyWorkbook($rows)])
        ->assertSessionHasNoErrors();

    expect(Competency::query()->where('code', 'C001')->exists())->toBeTrue();
});

test('invalid rows show Excel row errors and roll back the complete import', function () {
    CompetencyGroup::factory()->create(['code' => 'A']);

    $rows = [
        ['Kode', 'Kelompok', 'Nama Kompetensi', 'Definisi'],
        ['C001', 'A', 'Test-only competency', 'Test-only definition'],
        ['C001', 'A', 'Duplicate row', 'Duplicate definition'],
        ['C999', 'A', 'Invalid code', 'Test-only definition'],
        ['C002', 'Z', 'Invalid group', 'Test-only definition'],
        ['C003', 'A', null, 'Test-only definition'],
    ];

    $this->actingAs(competencyImporter())
        ->from(route('admin.competencies.index'))
        ->post(route('admin.competencies.import'), ['file' => competencyWorkbook($rows)])
        ->assertRedirect(route('admin.competencies.index'))
        ->assertSessionHasErrors(['row_3', 'row_4', 'row_5', 'row_6']);

    expect(Competency::query()->count())->toBe(0)
        ->and(AuditLog::query()->where('event', 'competency.imported')->count())->toBe(0);
});

test('a workbook with other headers is rejected with a pointer to the template', function () {
    competencyGroups();

    $rows = [['Nomor', 'Judul'], ['1', 'Something']];

    $this->actingAs(competencyImporter())
        ->post(route('admin.competencies.import'), ['file' => competencyWorkbook($rows)])
        ->assertSessionHasErrors(['file' => 'Header tidak sesuai template. Kolom wajib: Kode, Kelompok, Nama Kompetensi, dan Definisi (satu kali). Unduh ulang template jika perlu.']);

    expect(Competency::query()->count())->toBe(0);
});

test('a CSV upload is rejected because only Excel files are accepted', function () {
    competencyGroups();
    $csv = "code,group_code,name,description\nC001,A,Test-only competency,Test-only definition\n";

    $this->actingAs(competencyImporter())
        ->post(route('admin.competencies.import'), [
            'file' => UploadedFile::fake()->createWithContent('competencies.csv', $csv),
        ])
        ->assertSessionHasErrors(['file' => 'File harus berformat Excel (.xlsx).']);

    expect(Competency::query()->count())->toBe(0);
});

test('a file renamed to xlsx that is not a workbook is rejected', function () {
    competencyGroups();

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

test('the template lists all 62 codes, fills in existing competencies and offers the groups as a dropdown', function () {
    $groups = competencyGroups();
    Competency::factory()->create([
        'group_id' => $groups['C'],
        'code' => 'C005',
        'name' => 'Existing competency',
        'description' => 'Existing definition',
    ]);

    $response = $this->actingAs(competencyImporter())
        ->get(route('admin.competencies.template'))
        ->assertOk()
        ->assertDownload('template-master-kompetensi.xlsx');

    $path = tempnam(sys_get_temp_dir(), 'template-test-');
    file_put_contents($path, $response->streamedContent() ?: $response->baseResponse->getFile()->getContent());
    $rows = (new XlsxReader)->firstSheetRows($path);

    $zip = new ZipArchive;
    $zip->open($path);
    $sheetXml = (string) $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    unlink($path);

    expect($rows)->toHaveCount(63)
        ->and($rows[0]['cells'])->toBe(['Kode', 'Kelompok', 'Nama Kompetensi', 'Definisi'])
        ->and($rows[1]['cells'][0])->toBe('C001')
        ->and($rows[5]['cells'])->toBe(['C005', 'C', 'Existing competency', 'Existing definition'])
        ->and($rows[62]['cells'][0])->toBe('C062')
        ->and($sheetXml)->toContain('sqref="B2:B63"')
        ->and($sheetXml)->toContain('"A,B,C,D,E,F"');
});

test('a downloaded template that is filled in can be imported again', function () {
    competencyGroups();
    $admin = competencyImporter();

    $response = $this->actingAs($admin)->get(route('admin.competencies.template'));
    $template = tempnam(sys_get_temp_dir(), 'template-test-');
    file_put_contents($template, $response->baseResponse->getFile()->getContent());
    $rows = array_map(fn (array $row): array => $row['cells'], (new XlsxReader)->firstSheetRows($template));
    unlink($template);

    $rows[1] = ['C001', 'A', 'Filled from template', 'Template definition'];

    $this->actingAs($admin)
        ->post(route('admin.competencies.import'), ['file' => competencyWorkbook($rows)])
        ->assertSessionHasNoErrors();

    expect(Competency::query()->pluck('name', 'code')->all())->toBe(['C001' => 'Filled from template']);
});

test('users without competencies manage permission cannot download the template', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('admin.competencies.template'))
        ->assertForbidden();
});
