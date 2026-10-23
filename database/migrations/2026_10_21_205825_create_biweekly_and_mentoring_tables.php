<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('biweekly_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('period_no');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->date('meeting_date')->nullable();
            $table->text('agenda')->nullable();
            $table->text('summary')->nullable();
            $table->text('planned')->nullable();
            $table->text('actual')->nullable();
            $table->text('issues')->nullable();
            $table->text('decisions')->nullable();
            $table->string('status', 24)->default('DRAFT');
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['project_id', 'period_no'], 'uq_biweekly_project_period');
            $table->index(['project_id', 'status'], 'idx_biweekly_project_status');
        });

        Schema::create('biweekly_attendees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('biweekly_report_id')->constrained('biweekly_reports')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();

            $table->unique(['biweekly_report_id', 'user_id'], 'uq_biweekly_attendee');
        });

        Schema::create('project_mentors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('mentor_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->string('status', 16)->default('ACTIVE');
            $table->timestamp('assigned_at');
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();

            $table->index(['mentor_id', 'status', 'project_id'], 'idx_project_mentors_mentor');
            $table->index(['project_id', 'status'], 'idx_project_mentors_project');
        });

        Schema::create('mentoring_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_mentor_id')->constrained('project_mentors')->restrictOnDelete();
            $table->dateTime('session_at');
            $table->string('topic');
            $table->text('findings')->nullable();
            $table->text('suggestions')->nullable();
            $table->text('next_steps')->nullable();
            $table->string('status', 24)->default('DRAFT');
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('acknowledged_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('acknowledged_at')->nullable();
            $table->text('team_response')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->index(['project_id', 'session_at'], 'idx_mentoring_project_session');
            $table->index(['project_mentor_id', 'status'], 'idx_mentoring_mentor_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mentoring_reports');
        Schema::dropIfExists('project_mentors');
        Schema::dropIfExists('biweekly_attendees');
        Schema::dropIfExists('biweekly_reports');
    }
};
