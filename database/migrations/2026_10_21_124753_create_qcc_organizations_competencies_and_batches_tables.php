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
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('competency_groups', function (Blueprint $table) {
            $table->id();
            $table->string('code', 10)->unique();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('competency_master', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('competency_groups')->restrictOnDelete();
            $table->string('code', 10)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('qcc_batches', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->date('starts_on');
            $table->date('ends_on');
            $table->string('status', 32);
            $table->json('configuration_json')->nullable();
            $table->timestamps();

            $table->index(['status', 'starts_on', 'ends_on']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('qcc_batches');
        Schema::dropIfExists('competency_master');
        Schema::dropIfExists('competency_groups');
        Schema::dropIfExists('organizations');
    }
};
