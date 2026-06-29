<?php

namespace App\Filament\Concerns;

use App\Support\BranchContext;

trait SuperAdminOnlyResource
{
    public static function canViewAny(): bool
    {
        return BranchContext::isSuperAdmin();
    }

    public static function canCreate(): bool
    {
        return BranchContext::isSuperAdmin();
    }

    public static function canEdit($record): bool
    {
        return BranchContext::isSuperAdmin();
    }

    public static function canDelete($record): bool
    {
        return BranchContext::isSuperAdmin();
    }

    public static function canView($record): bool
    {
        return BranchContext::isSuperAdmin();
    }
}
