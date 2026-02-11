<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Check if admin user already exists
        if (User::where('email', 'admin@admin.com')->exists()) {
            $this->command->warn('Admin user already exists!');
            return;
        }

        User::create([
            'uuid' => Str::uuid()->toString(),
            'firebase_uid' => Str::uuid()->toString(), // Generate a UUID for firebase_uid
            'name' => 'admin',
            'email' => 'admin@admin.com',
            'password' => bcrypt('123456789'), // Change this password in production!
            'email_verified_at' => now(),
        ]);

        $this->command->info('Admin user created successfully!');
        $this->command->info('Email: admin@admin.com');
        $this->command->warn('Password: 123456789 (Please change this in production!)');
    }
}
