<?php

use App\Services\CompetencyImportService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Competencies become a flat skill dictionary without groups. On an installation that already
     * has a competency master, the refined dictionary (H01-H48, S01-S18) is loaded and every older
     * competency is switched off. Nothing is deleted: entries on the old competencies stay in the
     * database but no longer show or count anywhere. A fresh installation gets the dictionary from
     * the seeder instead.
     */
    public function up(): void
    {
        $groupColumn = collect(Schema::getColumns('competency_master'))->firstWhere('name', 'group_id');

        if (! ($groupColumn['nullable'] ?? false)) {
            Schema::table('competency_master', function (Blueprint $table): void {
                $table->foreignId('group_id')->nullable()->change();
            });
        }

        $path = database_path('data/competencies.csv');

        if (! DB::table('competency_master')->exists() || ! is_file($path)) {
            return;
        }

        app(CompetencyImportService::class)->importCsv($path, recordAudit: false);

        $dictionaryCodes = array_map(
            static fn (array $row): string => strtoupper(trim($row[0])),
            array_slice(array_map(
                static fn (string $line): array => str_getcsv($line, ',', '"', ''),
                file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [],
            ), 1),
        );

        DB::table('competency_master')->whereIn('code', $dictionaryCodes)->update(['is_active' => true, 'updated_at' => now()]);
        DB::table('competency_master')->whereNotIn('code', $dictionaryCodes)->update(['is_active' => false, 'updated_at' => now()]);
    }

    /**
     * Group stays optional on the way back: the dictionary skills have none to restore.
     */
    public function down(): void
    {
        DB::table('competency_master')->where('code', 'like', 'C%')->update(['is_active' => true]);
        DB::table('competency_master')->where('code', 'not like', 'C%')->update(['is_active' => false]);
    }
};
