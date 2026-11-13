<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Work and personal contact become one mobile number. The personal contact is usually
     * the mobile number, so it wins; the work contact fills in when it is empty.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('phone_number')->nullable()->after('work_location');
        });

        DB::table('users')->update([
            'phone_number' => DB::raw("COALESCE(NULLIF(TRIM(personal_contact), ''), NULLIF(TRIM(work_contact), ''))"),
        ]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['work_contact', 'personal_contact']);
        });
    }

    /**
     * Put the number back as the personal contact; the work contact cannot be told apart.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('work_contact')->nullable()->after('work_location');
            $table->string('personal_contact')->nullable()->after('work_contact');
        });

        DB::table('users')->update(['personal_contact' => DB::raw('phone_number')]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('phone_number');
        });
    }
};
