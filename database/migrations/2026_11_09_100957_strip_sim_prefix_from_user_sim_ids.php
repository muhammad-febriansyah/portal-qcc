<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Keep only the number of a SIM ID ("SIM2551" becomes "2551"). Passwords stay as they are.
     * An ID whose number already belongs to another account is left unchanged.
     */
    public function up(): void
    {
        DB::table('users')
            ->where('sim_id', 'like', 'SIM%')
            ->orderBy('id')
            ->get(['id', 'sim_id'])
            ->each(function (object $user): void {
                if (preg_match('/^SIM-?(\d+)$/i', (string) $user->sim_id, $match) !== 1) {
                    return;
                }

                if (DB::table('users')->where('sim_id', $match[1])->exists()) {
                    return;
                }

                DB::table('users')->where('id', $user->id)->update(['sim_id' => $match[1]]);
            });
    }

    /**
     * Put the "SIM" prefix back on number-only SIM IDs.
     */
    public function down(): void
    {
        DB::table('users')
            ->whereNotNull('sim_id')
            ->orderBy('id')
            ->get(['id', 'sim_id'])
            ->filter(fn (object $user): bool => preg_match('/^\d+$/', (string) $user->sim_id) === 1)
            ->each(function (object $user): void {
                if (DB::table('users')->where('sim_id', 'SIM'.$user->sim_id)->exists()) {
                    return;
                }

                DB::table('users')->where('id', $user->id)->update(['sim_id' => 'SIM'.$user->sim_id]);
            });
    }
};
