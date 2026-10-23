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
        Schema::create('overdue_reminder_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            $table->string('work_type', 32);
            $table->unsignedBigInteger('work_id');
            $table->date('due_date');
            $table->date('scheduled_for');
            $table->timestamps();

            $table->unique(
                ['recipient_id', 'work_type', 'work_id', 'due_date', 'scheduled_for'],
                'uq_overdue_reminder_recipient_work_day',
            );
            $table->index(['work_type', 'work_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('overdue_reminder_deliveries');
    }
};
