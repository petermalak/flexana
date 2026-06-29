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
        $superAdminRole = Role::findOrCreate(BranchContext::ROLE_SUPER_ADMIN, 'web');
        Role::findOrCreate(BranchContext::ROLE_BRANCH_ADMIN, 'web');

        $admin = User::query()->where('email', 'admin@admin.com')->first();

        if ($admin !== null && ! $admin->hasRole(BranchContext::ROLE_SUPER_ADMIN)) {
            $admin->syncRoles([BranchContext::ROLE_SUPER_ADMIN]);
            $admin->forceFill(['branch_id' => null])->save();
        }

        if ($admin !== null) {
            $this->command?->info('Assigned super_admin role to admin@admin.com');
        }
    }
}
