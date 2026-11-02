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
        Schema::create('qcc_batch_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('batch_id')->constrained('qcc_batches')->restrictOnDelete();
            $table->string('schedule_type', 16);
            $table->unsignedSmallInteger('sequence_no');
            $table->date('scheduled_on');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['batch_id', 'schedule_type', 'sequence_no'], 'uq_batch_schedule_sequence');
            $table->index(['scheduled_on', 'schedule_type'], 'idx_batch_schedule_due');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('qcc_batch_schedules');
    }
};
