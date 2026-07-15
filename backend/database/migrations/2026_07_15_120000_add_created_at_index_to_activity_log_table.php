<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The activity log is read newest-first on every request
 * (`ORDER BY created_at DESC, id DESC`), but spatie's published table only
 * indexes `log_name` / `subject` / `causer`. Index `created_at` so that sort —
 * and the date-range filter — is a cheap index scan rather than a full-table
 * filesort as the table grows. (InnoDB appends the primary key to secondary
 * indexes, so this also covers the `id` tie-breaker.)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
        });
    }
};
