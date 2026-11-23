<?php

use App\Enums\ThemeCategory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Move proposals to the new concept of the proposal form.
     *
     * The five new theme categories have no counterpart among the old ones, so a proposal that
     * still carries an old category loses it and its proposer picks a new one.
     *
     * "Latar belakang" and "Situasi saat ini & baseline" are now one field, "Latar belakang &
     * baseline data", so the old situation text is appended to the background. The old
     * situation column is left as it was.
     */
    public function up(): void
    {
        DB::table('projects')
            ->whereNotNull('theme_category')
            ->whereNotIn('theme_category', array_column(ThemeCategory::cases(), 'value'))
            ->update(['theme_category' => null]);

        DB::table('projects')
            ->whereNotNull('current_situation')
            ->where('current_situation', '!=', '')
            ->orderBy('id')
            ->chunkById(100, function ($projects): void {
                foreach ($projects as $project) {
                    $background = trim((string) $project->background);
                    $situation = trim((string) $project->current_situation);

                    if ($situation === '' || str_contains($background, $situation)) {
                        continue;
                    }

                    DB::table('projects')->where('id', $project->id)->update([
                        'background' => trim($background."\n\n".$situation),
                    ]);
                }
            });
    }

    /**
     * Reverse the migrations. The cleared categories and the merged text cannot be split back.
     */
    public function down(): void
    {
        //
    }
};
