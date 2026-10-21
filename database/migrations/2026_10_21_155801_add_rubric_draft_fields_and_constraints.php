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
        Schema::table('rubric_sets', function (Blueprint $table) {
            $table->string('scoring_type', 40)->unique();
            $table->string('name', 150);
        });

        Schema::table('rubric_versions', function (Blueprint $table) {
            $table->foreignId('rubric_set_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('version_number');
            $table->string('publication_status', 20)->default('draft');
            $table->unique(['rubric_set_id', 'version_number']);
            $table->index(['publication_status', 'rubric_set_id']);
        });

        Schema::table('rubric_criteria', function (Blueprint $table) {
            $table->foreignId('rubric_version_id')->constrained()->cascadeOnDelete();
            $table->string('code', 32);
            $table->string('name', 150);
            $table->text('description')->nullable();
            $table->decimal('weight_pct', 5, 2)->default(0);
            $table->unsignedSmallInteger('position');
            $table->unique(['rubric_version_id', 'code']);
            $table->index(['rubric_version_id', 'position']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rubric_criteria', function (Blueprint $table) {
            $table->dropUnique(['rubric_version_id', 'code']);
            $table->dropIndex(['rubric_version_id', 'position']);
            $table->dropForeign(['rubric_version_id']);
            $table->dropColumn([
                'rubric_version_id',
                'code',
                'name',
                'description',
                'weight_pct',
                'position',
            ]);
        });

        Schema::table('rubric_versions', function (Blueprint $table) {
            $table->dropUnique(['rubric_set_id', 'version_number']);
            $table->dropIndex(['publication_status', 'rubric_set_id']);
            $table->dropForeign(['rubric_set_id']);
            $table->dropColumn(['rubric_set_id', 'version_number', 'publication_status']);
        });

        Schema::table('rubric_sets', function (Blueprint $table) {
            $table->dropUnique(['scoring_type']);
            $table->dropColumn(['scoring_type', 'name']);
        });
    }
};
