<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('system_settings')
            ->where('key', 'attachments_max_size_mb')
            ->whereRaw('CAST(value AS UNSIGNED) < ?', [100])
            ->update(['value' => '100']);
    }

    public function down(): void
    {
        DB::table('system_settings')
            ->where('key', 'attachments_max_size_mb')
            ->whereRaw('CAST(value AS UNSIGNED) = ?', [100])
            ->update(['value' => '50']);
    }
};
