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
            ['email' => 'admin@marketplace.test'],
            [
                'name' => 'Marketplace Admin',
                'password' => 'password',
                'role' => User::ROLE_ADMIN,
                'email_verified_at' => now(),
            ]
        );

        User::firstOrCreate(
            ['email' => 'dev@marketplace.test'],
            [
                'name' => 'Demo Developer',
                'password' => 'password',
                'role' => User::ROLE_DEVELOPER,
                'email_verified_at' => now(),
            ]
        );
    }
}
