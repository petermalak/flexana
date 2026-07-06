<?php

namespace App\Filament\Concerns;

use App\Support\BranchContext;
use Illuminate\Database\Eloquent\Builder;

trait ScopesToUserBranch
{
    protected static function applyBranchScope(Builder $query, string $column = 'branch_id'): Builder
    {
        $branchId = BranchContext::scopedBranchId();

        if ($branchId === null) {
            return $query;
        }

        return $query->where($column, $branchId);
    }

    protected static function applyBranchScopeViaRelation(
        Builder $query,
        string $relation,
        string $column = 'branch_id',
    ): Builder {
        $branchId = BranchContext::scopedBranchId();

        if ($branchId === null) {
            return $query;
        }

        return $query->whereHas($relation, fn (Builder $relationQuery) => $relationQuery->where($column, $branchId));
    }

    protected static function superAdminOnly(): bool
    {
        return BranchContext::isSuperAdmin();
    }
}
