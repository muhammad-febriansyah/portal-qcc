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
        Schema::create('project_evidence', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('title', 200);
            $table->string('category', 40);
            $table->text('description')->nullable();
            $table->string('source_type', 16)->default('FILE');
            $table->string('storage_disk', 64);
            $table->string('storage_key', 512);
            $table->string('original_name', 255);
            $table->string('file_extension', 12);
            $table->string('mime_type', 127);
            $table->unsignedBigInteger('size_bytes');
            $table->char('checksum_sha256', 64);
            $table->uuid('version_group');
            $table->unsignedSmallInteger('version_number')->default(1);
            $table->foreignId('supersedes_id')->nullable()->constrained('project_evidence')->nullOnDelete();
            $table->string('scan_status', 24)->default('PENDING');
            $table->string('scan_error', 500)->nullable();
            $table->string('processing_status', 24)->default('PENDING');
            $table->string('extraction_status', 24)->default('PENDING');
            $table->string('processing_error', 500)->nullable();
            $table->string('thumbnail_path', 512)->nullable();
            $table->string('extracted_text_path', 512)->nullable();
            $table->json('metadata_json')->nullable();
            $table->timestamps();

            $table->unique(['version_group', 'version_number'], 'uq_evidence_version');
            $table->index(['project_id', 'scan_status', 'created_at'], 'idx_evidence_project_scan');
            $table->index(['project_id', 'version_group', 'version_number'], 'idx_evidence_project_version');
        });

        Schema::create('evidence_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('evidence_id')->constrained('project_evidence')->cascadeOnDelete();
            $table->string('target_type', 255);
            $table->unsignedBigInteger('target_id');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['evidence_id', 'target_type', 'target_id'], 'uq_evidence_target');
            $table->index(['target_type', 'target_id'], 'idx_evidence_link_target');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('evidence_links');
        Schema::dropIfExists('project_evidence');
    }
};
