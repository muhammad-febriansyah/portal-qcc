<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Old five-step wizard position mapped to the new seven-step wizard.
     *
     * @var array<int, int>
     */
    private const WIZARD_STEP_MAP = [1 => 1, 2 => 2, 3 => 3, 4 => 5, 5 => 7];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->longText('cost_projection')->nullable()->after('supplementary_information');
            $table->json('step_timeline_json')->nullable()->after('cost_projection');
        });

        // Highest first, so a remapped row is never moved twice.
        foreach (array_reverse(self::WIZARD_STEP_MAP, true) as $old => $new) {
            if ($old !== $new) {
                DB::table('projects')->where('wizard_step', $old)->update(['wizard_step' => $new]);
            }
        }

        // "Situasi saat ini" and "Baseline" are now one field. Keep the old baseline text visible there.
        DB::table('projects')
            ->whereNotNull('baseline')
            ->where('baseline', '!=', '')
            ->orderBy('id')
            ->chunkById(100, function ($projects): void {
                foreach ($projects as $project) {
                    $situation = trim((string) $project->current_situation);

                    if (str_contains($situation, trim((string) $project->baseline))) {
                        continue;
                    }

                    DB::table('projects')->where('id', $project->id)->update([
                        'current_situation' => trim($situation."\n\n**Baseline:** ".trim((string) $project->baseline)),
                    ]);
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (self::WIZARD_STEP_MAP as $old => $new) {
            if ($old !== $new) {
                DB::table('projects')->where('wizard_step', $new)->update(['wizard_step' => $old]);
            }
        }

        DB::table('projects')->where('wizard_step', '>', 5)->update(['wizard_step' => 5]);

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['cost_projection', 'step_timeline_json']);
        });
    }
};
