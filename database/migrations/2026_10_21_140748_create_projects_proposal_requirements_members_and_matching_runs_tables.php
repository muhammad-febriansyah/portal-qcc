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
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('qcc_batches')->restrictOnDelete();
            $table->foreignId('proposer_id')->constrained('users')->restrictOnDelete();
            $table->string('title');
            $table->text('theme')->nullable();
            $table->longText('background')->nullable();
            $table->longText('problem_statement')->nullable();
            $table->longText('current_situation')->nullable();
            $table->longText('baseline')->nullable();
            $table->longText('target')->nullable();
            $table->text('scope')->nullable();
            $table->json('learning_goals_json')->nullable();
            $table->longText('rca')->nullable();
            $table->longText('improvement_plan')->nullable();
            $table->longText('business_benefit')->nullable();
            $table->foreignId('pic_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('proposal_status', 32)->default('DRAFT');
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['batch_id', 'proposal_status', 'updated_at']);
            $table->index(['proposer_id', 'proposal_status']);
        });

        Schema::create('project_requirements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('competency_id')->constrained('competency_master')->restrictOnDelete();
            $table->unsignedTinyInteger('minimum_level')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedTinyInteger('priority')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'competency_id']);
        });

        Schema::create('project_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('role_in_project', 16);
            $table->string('status', 32);
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'user_id']);
            $table->index(['user_id', 'status', 'project_id']);
        });

        Schema::create('team_matching_runs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 32);
            $table->json('candidate_snapshot_json')->nullable();
            $table->json('output_json')->nullable();
            $table->string('model')->nullable();
            $table->string('prompt_version')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->string('error_code')->nullable();
            $table->json('token_usage_json')->nullable();
            $table->string('idempotency_key', 100)->nullable();
            $table->string('request_hash', 64)->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'idempotency_key']);
            $table->index(['project_id', 'status', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('team_matching_runs');
        Schema::dropIfExists('project_members');
        Schema::dropIfExists('project_requirements');
        Schema::dropIfExists('projects');
    }
};
