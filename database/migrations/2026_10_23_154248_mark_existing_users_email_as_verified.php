<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Email verification is required from now on. Accounts that exist already were created
     * by an administrator or the seeders, so they are treated as verified and keep access.
     */
    public function up(): void
    {
        DB::table('users')
            ->whereNull('email_verified_at')
            ->update(['email_verified_at' => now()]);
    }

    /**
     * The previous state cannot be restored, because it is not known which accounts were unverified.
     */
    public function down(): void {}
};
