<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qcc_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('step_number');
            $table->text('activity_description')->nullable();
            $table->text('plan_text')->nullable();
            $table->text('actual_text')->nullable();
            $table->date('target_date')->nullable();
            $table->string('status', 32)->default('NOT_STARTED');
            $table->unsignedTinyInteger('progress_pct')->default(0);
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedInteger('lock_version')->default(0);
            $table->timestamps();

            $table->unique(['project_id', 'step_number'], 'uq_qcc_step_project_number');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qcc_steps');
    }
};
