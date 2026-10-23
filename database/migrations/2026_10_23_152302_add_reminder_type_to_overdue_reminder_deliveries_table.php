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
        Schema::table('overdue_reminder_deliveries', function (Blueprint $table) {
            $table->string('reminder_type', 20)->default('OVERDUE')->after('work_id');
            $table->dropUnique('uq_overdue_reminder_recipient_work_day');
            $table->unique(
                ['recipient_id', 'work_type', 'work_id', 'reminder_type', 'due_date', 'scheduled_for'],
                'uq_overdue_reminder_type_delivery',
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('overdue_reminder_deliveries', function (Blueprint $table) {
            $table->dropUnique('uq_overdue_reminder_type_delivery');
            $table->dropColumn('reminder_type');
            $table->unique(
                ['recipient_id', 'work_type', 'work_id', 'due_date', 'scheduled_for'],
                'uq_overdue_reminder_recipient_work_day',
            );
        });
    }
};
