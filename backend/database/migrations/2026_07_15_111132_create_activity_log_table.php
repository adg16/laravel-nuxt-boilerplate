<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The audit-trail table backing spatie/laravel-activitylog (v5). Each row is one
 * logged event: a polymorphic `subject` (the User/Role that changed, nullable
 * for non-model events like a settings change), a polymorphic `causer` (the
 * acting user, resolved from the `web` guard — see config/activitylog.php), an
 * `event` (created/updated/deleted), and a `properties`/`attribute_changes` JSON
 * diff. Published from the package unchanged except for these notes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_log', function (Blueprint $table) {
            $table->id();
            $table->string('log_name')->nullable()->index();
            $table->text('description');
            $table->nullableMorphs('subject', 'subject');
            $table->string('event')->nullable();
            $table->nullableMorphs('causer', 'causer');
            $table->json('attribute_changes')->nullable();
            $table->json('properties')->nullable();
            $table->timestamps();

            // The log is read newest-first on every request
            // (`ORDER BY created_at DESC, id DESC`); spatie's published table only
            // indexes log_name/subject/causer. Index created_at so that sort — and
            // the date-range filter — is a cheap index scan rather than a full-table
            // filesort as the table grows. (InnoDB appends the PK, covering the `id`
            // tie-breaker.)
            $table->index('created_at');
        });
    }
};
