<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call(RolePermissionSeeder::class);

        // Seed the "System" account FIRST so it takes id 1. id 1 is the
        // predictable target attackers assume is the top admin — making it the
        // login-less, permission-less service account means that assumption
        // buys them nothing. It has no roles/permissions and an unusable random
        // password so it can't be logged into; it exists purely to attribute
        // app-generated activity (scheduled/automated events) with no human
        // actor — resolve it later via User::system().
        // Seeded accounts didn't go through the invite flow — mark them verified
        // so they don't show as "pending" in the UI.
        User::firstOrCreate(
            ['email' => config('app.system_user_email')],
            [
                'name' => 'System',
                'password' => Hash::make(Str::random(40)),
            ]
        )->markVerified();

        $admin = User::firstOrCreate(
            ['email' => config('users.default_user.email')],
            [
                'name' => 'Super Admin',
                'password' => bcrypt(config('users.default_user.password')),
            ]
        );
        $admin->markVerified();
        $admin->assignRole('super-admin');
    }
}
