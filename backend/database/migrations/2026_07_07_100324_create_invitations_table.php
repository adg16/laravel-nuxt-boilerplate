<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Pending user invitations. Keyed by e-mail (one live invite per address),
     * mirroring password_reset_tokens — the token is stored hashed and rows are
     * deleted once the invite is accepted. Kept separate from the password-reset
     * flow so invites have their own lifetime and messaging.
     */
    public function up(): void
    {
        Schema::create('invitations', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};
