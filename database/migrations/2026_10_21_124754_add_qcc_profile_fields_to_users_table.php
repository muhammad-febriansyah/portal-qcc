<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('organization_id')->nullable()->constrained('organizations')->nullOnDelete();
            $table->string('position')->nullable();
            $table->string('work_location')->nullable();
            $table->string('work_contact')->nullable();
            $table->string('personal_contact')->nullable();
            $table->string('availability_status', 32)->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('organization_id');
            $table->dropColumn([
                'position',
                'work_location',
                'work_contact',
                'personal_contact',
                'availability_status',
            ]);
        });
    }
};
