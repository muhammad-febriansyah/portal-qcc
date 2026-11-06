<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The three program phases of a batch and its participant target, shown on the program dashboard.
     */
    public function up(): void
    {
        Schema::table('qcc_batches', function (Blueprint $table) {
            $table->unsignedInteger('participant_target')->nullable()->after('ends_on');
            $table->date('registration_starts_on')->nullable()->after('participant_target');
            $table->date('registration_ends_on')->nullable()->after('registration_starts_on');
            $table->date('selection_starts_on')->nullable()->after('registration_ends_on');
            $table->date('selection_ends_on')->nullable()->after('selection_starts_on');
            $table->date('execution_starts_on')->nullable()->after('selection_ends_on');
            $table->date('execution_ends_on')->nullable()->after('execution_starts_on');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('qcc_batches', function (Blueprint $table) {
            $table->dropColumn([
                'participant_target',
                'registration_starts_on',
                'registration_ends_on',
                'selection_starts_on',
                'selection_ends_on',
                'execution_starts_on',
                'execution_ends_on',
            ]);
        });
    }
};
