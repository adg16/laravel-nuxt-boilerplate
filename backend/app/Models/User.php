<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, Notifiable;

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
}
