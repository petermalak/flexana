<?php

namespace App\Policies;

use App\Infrastructure\Persistence\Eloquent\BookingModel;
use App\Models\User;
use App\Support\BranchContext;

class BookingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isBranchAdmin();
    }

    public function view(User $user, BookingModel $booking): bool
    {
        return $this->canAccessBooking($booking);
    }

    public function create(User $user): bool
    {
        return $user->isSuperAdmin() || $user->isBranchAdmin();
    }

    public function update(User $user, BookingModel $booking): bool
    {
        return $this->canAccessBooking($booking);
    }

    public function delete(User $user, BookingModel $booking): bool
    {
        return $this->canAccessBooking($booking);
    }

    private function canAccessBooking(BookingModel $booking): bool
    {
        if (! BranchContext::isScoped()) {
            return true;
        }

        $appointment = $booking->relationLoaded('appointment')
            ? $booking->appointment
            : $booking->appointment()->first();

        if ($appointment === null) {
            return false;
        }

        return BranchContext::canAccessBranch($appointment->branch_id);
    }
}
