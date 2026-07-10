<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records which second factor a user enrolled ('totp' | 'email'). TOTP users
 * also have a `two_factor_secret`; email users don't (their code is delivered
 * per-login), so this column is what distinguishes an enrolled email user.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('two_factor_method')->after('two_factor_secret')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('two_factor_method');
        });
    }
};
