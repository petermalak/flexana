<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed a default admin user for login
        // Note: password is set as plain text because User model has 'password' => 'hashed' cast
        User::query()->updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'uuid' => Str::uuid()->toString(),
                'firebase_uid' => 'admin-seeder-user',
                'name' => 'Admin User',
                'password' => 'password123',
            ],
        );
    }
}
