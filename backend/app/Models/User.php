<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\TwoFactorMethod;
use App\Notifications\QueuedResetPassword;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes', 'two_factor_method', 'avatar_path'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable, TwoFactorAuthenticatable;

    /**
     * The disk avatars live on — the app's default filesystem disk
     * (config/filesystems.php `default` / `FILESYSTEM_DISK`): the bundled MinIO
     * (`s3`) in dev, real S3 in prod, the private `local` disk in tests. Whatever
     * the disk, avatars are served through the authenticated avatar route rather
     * than a public URL.
     */
    public static function avatarDisk(): string
    {
        return config('filesystems.default');
    }

    protected static function booted(): void
    {
        // Clean up the avatar file when the user is deleted so nothing is
        // orphaned in storage.
        static::deleted(fn (User $user) => $user->deleteAvatar());
    }

    /**
     * Delete the user's avatar file (no-op when there's none). Does not touch the
     * column — callers null `avatar_path` themselves.
     */
    public function deleteAvatar(): void
    {
        if ($this->avatar_path) {
            Storage::disk(self::avatarDisk())->delete($this->avatar_path);
        }
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * The permission-less "System" service account, used to attribute
     * app-generated activity (scheduled/automated events) that has no human
     * actor. Reference it when logging such events, e.g.
     * `activity()->causedBy(User::system())`.
     */
    public static function system(): ?self
    {
        return static::where('email', config('app.system_user_email'))->first();
    }

    public function isSystem(): bool
    {
        return $this->email === config('app.system_user_email');
    }

    /**
     * The second factor the user enrolled, if any. Fortify only tracks a TOTP
     * secret; this column distinguishes email-based 2FA (which has no secret).
     */
    public function twoFactorMethod(): ?TwoFactorMethod
    {
        return $this->two_factor_method ? TwoFactorMethod::from($this->two_factor_method) : null;
    }

    /**
     * Whether the user has an active (confirmed) second factor — of *either*
     * method. Use this instead of Fortify's TwoFactorAuthenticatable::
     * hasEnabledTwoFactorAuthentication(), which is TOTP-only (it requires a
     * `two_factor_secret` an email user never has). `two_factor_confirmed_at` is
     * set by confirming either method, so it's the unified signal.
     */
    public function hasTwoFactorEnabled(): bool
    {
        return ! is_null($this->two_factor_confirmed_at);
    }

    /**
     * Null the enrolled method. The single place that column is reset — called
     * by both the self-disable action and the admin reset (Fortify's base
     * disable action clears the secret/recovery/confirmed columns but not this
     * one).
     */
    public function clearTwoFactorMethod(): void
    {
        $this->forceFill(['two_factor_method' => null])->save();
    }

    /**
     * Whether the user has accepted their invitation and set a password. We
     * reuse email_verified_at as the "onboarded" marker (accepting the invite
     * both sets the password and proves e-mail ownership).
     */
    public function isVerified(): bool
    {
        return ! is_null($this->email_verified_at);
    }

    public function markVerified(): void
    {
        $this->forceFill(['email_verified_at' => now()])->save();
    }

    /**
     * Protected accounts can't be edited or deleted through the management UI /
     * API: the super-admin (the Gate::before bypass) and the System account.
     */
    public function isProtected(): bool
    {
        return $this->isSystem() || $this->hasRole('super-admin');
    }

    /**
     * Send the password-reset link on a queue (see QueuedResetPassword) so a
     * mail failure never surfaces as a 500 on the forgot-password request.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new QueuedResetPassword($token));
    }
}
