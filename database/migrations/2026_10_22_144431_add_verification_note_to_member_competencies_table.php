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
        Schema::table('member_competencies', function (Blueprint $table): void {
            $table->text('verification_note')->nullable();
        });

        DB::table('member_competencies')
            ->whereRaw('LOWER(verification_status) = ?', ['verified'])
            ->update(['verification_status' => 'VERIFIED']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('member_competencies')
            ->where('verification_status', 'VERIFIED')
            ->update(['verification_status' => 'verified']);

        Schema::table('member_competencies', function (Blueprint $table): void {
            $table->dropColumn('verification_note');
        });
    }
};
