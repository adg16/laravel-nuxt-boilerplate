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
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();

            // Relative path to the uploaded avatar on the app's default filesystem
            // disk (s3/MinIO in dev, real S3 in prod, `local` in tests), or null.
            // Streamed back through an authenticated API route, never a public URL.
            $table->string('avatar_path')->nullable();

            $table->timestamp('email_verified_at')->nullable();

            // When set, the account is deactivated: it can't sign in and any live
            // session is cut off. Null means active. A nullable timestamp (not a
            // boolean) so we also record *when* it was deactivated.
            $table->timestamp('deactivated_at')->nullable();

            // Marks accounts that can't be edited/deleted through the management
            // API (the seeded super-admin and the System account — set by
            // DatabaseSeeder). A durable flag rather than deriving protection from
            // the mutable email, so changing the super-admin's email can't strip it.
            $table->boolean('is_protected')->default(false);

            $table->string('password');

            // Fortify two-factor columns. `confirm` is enabled, so only a
            // confirmed secret counts as "enabled" (two_factor_confirmed_at).
            // `two_factor_method` ('totp' | 'email') records the enrolled factor:
            // TOTP users also have a secret; email users don't (the code is
            // delivered per-login), so this column is what marks an email enrollee.
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->string('two_factor_method')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();

            // Blame columns: who created / last updated the row. Nullable (seeder,
            // console, or guest writes have no actor) and nullOnDelete so deleting
            // the acting user doesn't block/cascade.
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();

            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('users');
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('sessions');
    }
};
