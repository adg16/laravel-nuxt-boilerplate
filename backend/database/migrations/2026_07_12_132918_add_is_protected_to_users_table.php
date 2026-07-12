<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Marks accounts that can't be edited or deleted through the management API —
     * the seeded super-admin and the System account (set by DatabaseSeeder). A
     * durable flag rather than deriving protection from the (mutable) email, so
     * changing the super-admin's email can't strip its protection.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_protected')->default(false)->after('deactivated_at');
        });

        // Backfill existing installs so protection is correct even without a
        // re-seed. On a fresh install this matches nothing (users are seeded
        // after migrations) and the seeder sets the flag instead.
        DB::table('users')
            ->whereIn('email', array_filter([
                config('app.system_user_email'),
                config('users.default_user.email'),
            ]))
            ->update(['is_protected' => true]);
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_protected');
        });
    }
};
