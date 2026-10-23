<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('project_risks', function (Blueprint $table): void {
            $table->foreignId('pic_id')->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::table('project_risks')->whereNull('pic_id')->exists()) {
            throw new RuntimeException('Cannot restore required risk PIC while unassigned risks exist.');
        }

        Schema::table('project_risks', function (Blueprint $table): void {
            $table->foreignId('pic_id')->nullable(false)->change();
        });
    }
};
