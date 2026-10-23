<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('project_risks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('type', 16);
            $table->string('title');
            $table->text('description');
            $table->text('impact');
            $table->string('likelihood', 16);
            $table->string('priority', 16);
            $table->text('mitigation')->nullable();
            $table->foreignId('pic_id')->constrained('users')->restrictOnDelete();
            $table->date('due_date')->nullable();
            $table->string('status', 16)->default('OPEN');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'type', 'status'], 'idx_risks_project_type_status');
            $table->index(['project_id', 'priority', 'due_date'], 'idx_risks_project_priority_due');
            $table->index(['pic_id', 'status', 'due_date'], 'idx_risks_pic_status_due');
        });

        Schema::create('action_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('source_type', 32)->default('PROJECT');
            $table->unsignedBigInteger('source_id')->nullable();
            $table->string('title');
            $table->text('description')->nullable();
            $table->foreignId('pic_id')->constrained('users')->restrictOnDelete();
            $table->date('due_date');
            $table->string('status', 16)->default('OPEN');
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['pic_id', 'status', 'due_date'], 'idx_action_pic_status_due');
            $table->index(['project_id', 'status', 'due_date'], 'idx_action_project_status_due');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('action_items');
        Schema::dropIfExists('project_risks');
    }
};
