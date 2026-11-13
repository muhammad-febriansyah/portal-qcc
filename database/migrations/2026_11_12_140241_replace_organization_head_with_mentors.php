<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * An organization can have more than one mentor (LOB CCCM and KISS share two), so the single
     * head moves into a list of mentors. Each mentor reviews and mentors the organization's proposals.
     */
    public function up(): void
    {
        Schema::create('organization_mentors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['organization_id', 'user_id']);
        });

        $now = now();

        DB::table('organizations')->whereNotNull('head_id')->orderBy('id')->get(['id', 'head_id'])
            ->each(fn (object $organization) => DB::table('organization_mentors')->insert([
                'organization_id' => $organization->id,
                'user_id' => $organization->head_id,
                'created_at' => $now,
                'updated_at' => $now,
            ]));

        Schema::table('organizations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('head_id');
        });
    }

    public function down(): void
    {
        Schema::table('organizations', function (Blueprint $table) {
            $table->foreignId('head_id')->nullable()->after('name')->constrained('users')->nullOnDelete();
        });

        DB::table('organization_mentors')->orderBy('id')->get(['organization_id', 'user_id'])
            ->unique('organization_id')
            ->each(fn (object $mentor) => DB::table('organizations')
                ->where('id', $mentor->organization_id)
                ->update(['head_id' => $mentor->user_id]));

        Schema::dropIfExists('organization_mentors');
    }
};
