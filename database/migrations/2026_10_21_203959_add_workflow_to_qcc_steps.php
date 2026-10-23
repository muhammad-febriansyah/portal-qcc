<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('qcc_steps', function (Blueprint $table) {
            $table->text('submitter_note')->nullable()->after('progress_pct');
        });

        Schema::create('qcc_step_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('qcc_step_id')->constrained('qcc_steps')->cascadeOnDelete();
            $table->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 32);
            $table->string('previous_status', 32)->nullable();
            $table->string('new_status', 32);
            $table->unsignedInteger('version');
            $table->json('snapshot_json')->nullable();
            $table->text('reason')->nullable();
            $table->timestamp('created_at');

            $table->index(['qcc_step_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qcc_step_histories');

        Schema::table('qcc_steps', function (Blueprint $table) {
            $table->dropColumn('submitter_note');
        });
    }
};
