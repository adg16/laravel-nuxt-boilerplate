<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => env('DEFAULT_USER_EMAIL', 'admin@example.com')],
            [
                'name' => 'Admin',
                'password' => bcrypt(env('DEFAULT_USER_PASSWORD', 'password')),
            ]
        );
    }
}
