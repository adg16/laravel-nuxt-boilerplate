<?php

namespace App\Models\Concerns;

use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Stamps `created_by` / `updated_by` with the acting user's id on write.
 *
 * Only stamps when a user is authenticated, so rows written by the seeder,
 * console commands, or a guest (e.g. invitation acceptance) keep null blame
 * columns rather than being falsely attributed. Requires nullable `created_by`
 * / `updated_by` columns referencing `users.id` (see the add-blameable
 * migration). The columns aren't mass-assignable — the trait sets them
 * directly, bypassing the model's `#[Fillable]`.
 */
trait Blameable
{
    public static function bootBlameable(): void
    {
        static::creating(function ($model) {
            if ($id = auth()->id()) {
                // ??= so an explicitly-provided value (e.g. backfills) wins.
                $model->created_by ??= $id;
                $model->updated_by ??= $id;
            }
        });

        static::updating(function ($model) {
            if ($id = auth()->id()) {
                $model->updated_by = $id;
            }
        });
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
