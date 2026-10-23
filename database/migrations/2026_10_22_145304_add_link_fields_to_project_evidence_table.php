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
        Schema::table('project_evidence', function (Blueprint $table): void {
            $table->text('url')->nullable()->after('source_type');
            $table->text('summary')->nullable()->after('url');
            $table->string('storage_disk', 64)->nullable()->change();
            $table->string('storage_key', 512)->nullable()->change();
            $table->string('original_name', 255)->nullable()->change();
            $table->string('file_extension', 12)->nullable()->change();
            $table->string('mime_type', 127)->nullable()->change();
            $table->unsignedBigInteger('size_bytes')->nullable()->change();
            $table->char('checksum_sha256', 64)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_evidence', function (Blueprint $table): void {
            $table->dropColumn(['url', 'summary']);
        });
    }
};
