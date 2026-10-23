<?php

namespace Database\Seeders;

use App\Services\CompetencyImportService;
use Illuminate\Database\Seeder;
use Illuminate\Validation\ValidationException;

class CompetencyMasterSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(CompetencyImportService $importer): void
    {
        $path = database_path('data/competencies.csv');

        if (! is_file($path)) {
            $this->command?->info('Competency master CSV not found; skipping competency import.');

            return;
        }

        try {
            $counts = $importer->importCsv($path, recordAudit: false);
        } catch (ValidationException $exception) {
            $this->command?->error('Competency master CSV invalid: '.collect($exception->errors())->flatten()->implode(' '));

            throw $exception;
        }

        $this->command?->info("Imported {$counts['total']} competencies ({$counts['created']} created, {$counts['updated']} updated).");
    }
}
