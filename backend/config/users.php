<?php

return [
    /*
    | How new users are created. This is the *default* for the `user_creation_mode`
    | setting (see App\Enums\Setting) — it can be overridden at runtime from the
    | Settings UI, but this env value is the fallback when there's no override.
    |
    |   'choice'       → the admin picks per user: send an invite, or set a password
    |   'invite'       → always e-mail an invitation; the user sets their own password
    |   'set_password' → the admin always sets the password; the user is active at once
    */
    'creation_mode' => env('USER_CREATION_MODE', 'choice'),

    /*
    | The default super-admin seeded by DatabaseSeeder. Read via config (not
    | env() directly in the seeder) so it resolves correctly under cached config.
    */
    'default_user' => [
        'name' => env('DEFAULT_USER_NAME', 'Super Admin'),
        'email' => env('DEFAULT_USER_EMAIL', 'super.admin@example.com'),
        'password' => env('DEFAULT_USER_PASSWORD', 'password'),
    ],
];
