<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * When a person first had a complete profile, so the program dashboard can draw registrations over time.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('profile_completed_at')->nullable()->after('needs_profile_setup');
        });

        // Profiles completed before this column existed have no exact moment; their last update is the closest guess.
        DB::table('users')
            ->whereNotNull('organization_id')
            ->whereNotNull('position')->where('position', '!=', '')
            ->whereNotNull('work_location')->where('work_location', '!=', '')
            ->whereNotNull('work_contact')->where('work_contact', '!=', '')
            ->whereNotNull('personal_contact')->where('personal_contact', '!=', '')
            ->whereNotNull('availability_status')->where('availability_status', '!=', '')
            ->update(['profile_completed_at' => DB::raw('updated_at')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('profile_completed_at');
        });
    }
};
