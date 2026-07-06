<?php

use App\Models\User;
use App\Support\BranchContext;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    public function up(): void
    {
        Role::findOrCreate(BranchContext::ROLE_SUPER_ADMIN, 'web');
        Role::findOrCreate(BranchContext::ROLE_BRANCH_ADMIN, 'web');

        User::query()
            ->whereDoesntHave('roles')
            ->each(function (User $user): void {
                $user->assignRole(BranchContext::ROLE_SUPER_ADMIN);
            });
    }

    public function down(): void
    {
        // Roles are not removed on rollback.
    }
};
