<?php

namespace Database\Seeders;

use App\Models\User;
use App\Support\BranchContext;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class RolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        Role::findOrCreate(BranchContext::ROLE_SUPER_ADMIN, 'web');
        Role::findOrCreate(BranchContext::ROLE_BRANCH_ADMIN, 'web');

        User::query()
            ->whereDoesntHave('roles')
            ->each(function (User $user): void {
                $user->assignRole(BranchContext::ROLE_SUPER_ADMIN);
            });

        $admin = User::query()->where('email', 'admin@admin.com')->first();

        if ($admin !== null) {
            if (! $admin->hasRole(BranchContext::ROLE_SUPER_ADMIN)) {
                $admin->syncRoles([BranchContext::ROLE_SUPER_ADMIN]);
            }
            $admin->forceFill(['branch_id' => null])->save();
            $this->command?->info('Ensured super_admin role for admin@admin.com');
        }
    }
}
