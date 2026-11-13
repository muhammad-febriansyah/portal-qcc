<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The first five theme categories, mapped to the six categories that replaced them.
     *
     * @var array<string, string>
     */
    private const RENAMED = [
        'REVENUE_GM' => 'NEW_REVENUE_STREAM',
        'EFFICIENCY' => 'COST_EFFICIENCY',
        'RISK_COMPLIANCE_PROCESS_CONTROL' => 'SAFETY_RISK_COMPLIANCE',
    ];

    /**
     * Proposals keep their category under its new name. Quality & Customer Experience and
     * Productivity keep their names; Delivery & Service Excellence is new.
     */
    public function up(): void
    {
        foreach (self::RENAMED as $old => $new) {
            DB::table('projects')->where('theme_category', $old)->update(['theme_category' => $new]);
        }
    }

    /**
     * Reverse the migrations. Delivery & Service Excellence had no old category, so it is cleared.
     */
    public function down(): void
    {
        foreach (self::RENAMED as $old => $new) {
            DB::table('projects')->where('theme_category', $new)->update(['theme_category' => $old]);
        }

        DB::table('projects')->where('theme_category', 'DELIVERY_SERVICE_EXCELLENCE')->update(['theme_category' => null]);
    }
};
