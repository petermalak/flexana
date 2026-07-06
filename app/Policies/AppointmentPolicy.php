<?php

namespace App\Policies;

use App\Infrastructure\Persistence\Eloquent\AppointmentModel;
use App\Models\User;
use App\Support\BranchContext;

class AppointmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isBranchAdmin();
    }

    public function view(User $user, AppointmentModel $appointment): bool
    {
        return BranchContext::canAccessBranch($appointment->branch_id);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isBranchAdmin();
    }

    public function update(User $user, AppointmentModel $appointment): bool
    {
        return BranchContext::canAccessBranch($appointment->branch_id);
    }

    public function delete(User $user, AppointmentModel $appointment): bool
    {
        return BranchContext::canAccessBranch($appointment->branch_id);
    }
}
