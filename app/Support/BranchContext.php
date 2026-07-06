<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

final class BranchContext
{
    public const ROLE_SUPER_ADMIN = 'super_admin';

    public const ROLE_BRANCH_ADMIN = 'branch_admin';

    /**
     * Branch ID to filter admin data, or null when the user may see all branches.
     */
    public static function scopedBranchId(): ?int
    {
        $user = self::user();

        if ($user === null || $user->isSuperAdmin()) {
            return null;
        }

        $branchId = $user->branch_id;

        return $branchId !== null ? (int) $branchId : null;
    }

    public static function isScoped(): bool
    {
        return self::scopedBranchId() !== null;
    }

    public static function canAccessBranch(?int $branchId): bool
    {
        if (! self::isScoped()) {
            return true;
        }

        if ($branchId === null) {
            return false;
        }

        return (int) $branchId === self::scopedBranchId();
    }

    public static function isSuperAdmin(): bool
    {
        return self::user()?->isSuperAdmin() ?? false;
    }

    public static function user(): ?User
    {
        $user = Auth::user();

        return $user instanceof User ? $user : null;
    }

    public static function scopeAppointments(Builder $query): Builder
    {
        $branchId = self::scopedBranchId();

        if ($branchId === null) {
            return $query;
        }

        return $query->where($query->getModel()->getTable().'.branch_id', $branchId);
    }

    public static function scopeServicesForBranch(Builder $query): Builder
    {
        $branchId = self::scopedBranchId();

        if ($branchId === null) {
            return $query;
        }

        return $query->whereHas('branches', fn (Builder $branchQuery) => $branchQuery->whereKey($branchId));
    }

    public static function scopeCustomersWithBranchBookings(Builder $query): Builder
    {
        $branchId = self::scopedBranchId();

        if ($branchId === null) {
            return $query;
        }

        return $query->whereHas('bookings.appointment', fn (Builder $appointmentQuery) => $appointmentQuery->where('branch_id', $branchId));
    }
}
