<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\BranchContext;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Role;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $admin = User::query()->where('email', 'admin@admin.com')->first();

        if ($admin !== null) {
            Role::findOrCreate(BranchContext::ROLE_SUPER_ADMIN, 'web');
            if (! $admin->hasRole(BranchContext::ROLE_SUPER_ADMIN)) {
                $admin->syncRoles([BranchContext::ROLE_SUPER_ADMIN]);
            }
            $this->command->warn('Admin user already exists (super_admin role ensured).');

            return;
        }

        $admin = User::create([
            'uuid' => Str::uuid()->toString(),
            'firebase_uid' => Str::uuid()->toString(), // Generate a UUID for firebase_uid
            'name' => 'admin',
            'email' => 'admin@admin.com',
            'password' => bcrypt('123456789'), // Change this password in production!
            'email_verified_at' => now(),
        ]);

        Role::findOrCreate(BranchContext::ROLE_SUPER_ADMIN, 'web');
        $admin->assignRole(BranchContext::ROLE_SUPER_ADMIN);

        $this->command->info('Admin user created successfully!');
        $this->command->info('Email: admin@admin.com');
        $this->command->warn('Password: 123456789 (Please change this in production!)');
    }
}
