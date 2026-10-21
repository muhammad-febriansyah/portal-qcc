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
        Schema::create('scoring_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained('projects')->restrictOnDelete();
            $table->foreignId('rubric_version_id')->constrained('rubric_versions')->restrictOnDelete();
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32);
            $table->string('idempotency_key')->unique();
            $table->unsignedSmallInteger('attempt_count')->default(0);
            $table->string('model')->nullable();
            $table->string('prompt_version', 32)->nullable();
            $table->json('input_manifest_json')->nullable();
            $table->string('request_hash', 64)->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->string('error_code', 64)->nullable();
            $table->text('error_summary')->nullable();
            $table->json('usage_json')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();

            $table->index(['project_id', 'status', 'created_at']);
        });

        Schema::create('scoring_results', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scoring_run_id')->unique()->constrained('scoring_runs')->cascadeOnDelete();
            $table->text('overall_summary')->nullable();
            $table->json('cautions_json')->nullable();
            $table->decimal('ai_weighted_score', 5, 2)->nullable();
            $table->decimal('reviewer_weighted_score', 5, 2)->nullable();
            $table->decimal('final_score', 5, 2)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_comment')->nullable();
            $table->timestamps();
        });

        Schema::create('scoring_criterion_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scoring_result_id')->constrained('scoring_results')->cascadeOnDelete();
            $table->foreignId('rubric_criterion_id')->constrained('rubric_criteria')->restrictOnDelete();
            $table->decimal('ai_score', 5, 2);
            $table->decimal('reviewer_score', 5, 2)->nullable();
            $table->decimal('final_score', 5, 2)->nullable();
            $table->text('rationale');
            $table->json('gaps_json')->nullable();
            $table->text('uncertainty')->nullable();
            $table->json('evidence_references_json')->nullable();
            $table->text('override_reason')->nullable();
            $table->timestamps();

            $table->unique(['scoring_result_id', 'rubric_criterion_id'], 'scoring_scores_result_criterion_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('scoring_criterion_scores');
        Schema::dropIfExists('scoring_results');
        Schema::dropIfExists('scoring_runs');
    }
};
