<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The system now assigns the organization head as mentor, so no person assigns it.
     */
    public function up(): void
    {
        Schema::table('project_mentors', function (Blueprint $table) {
            $table->foreignId('assigned_by')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('project_mentors', function (Blueprint $table) {
            $table->foreignId('assigned_by')->nullable(false)->change();
        });
    }
};
