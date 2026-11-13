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
        $tableName = 'overdue_reminder_deliveries';

        // Each step is checked first so a partly applied run (column added, index not yet swapped) can finish.
        if (! Schema::hasColumn($tableName, 'reminder_type')) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->string('reminder_type', 20)->default('OVERDUE')->after('work_id');
            });
        }

        // The new index goes first: the old one still backs the recipient_id foreign key and cannot be dropped alone.
        if (! Schema::hasIndex($tableName, 'uq_overdue_reminder_type_delivery', 'unique')) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->unique(
                    ['recipient_id', 'work_type', 'work_id', 'reminder_type', 'due_date', 'scheduled_for'],
                    'uq_overdue_reminder_type_delivery',
                );
            });
        }

        if (Schema::hasIndex($tableName, 'uq_overdue_reminder_recipient_work_day', 'unique')) {
            Schema::table($tableName, function (Blueprint $table) {
                $table->dropUnique('uq_overdue_reminder_recipient_work_day');
            });
        }
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
