<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Stores the relative path (on the app's default filesystem disk — s3/MinIO in
 * dev, real S3 in prod, `local` in tests) to a user's uploaded avatar image, or
 * null when they have none. The file is streamed back through an authenticated
 * API route rather than a public URL, so the same-origin nginx (which only routes
 * /api|sanctum|up to PHP) can serve it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar_path')->after('email')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('avatar_path');
        });
    }
};
