<?php

namespace App\Support;

use App\Infrastructure\Persistence\Eloquent\BranchModel;
use Illuminate\Support\Facades\Cache;

final class BranchSettings
{
    private const CACHE_KEY = 'branches.default_id';

    public static function defaultBranchId(): ?int
    {
        return Cache::remember(self::CACHE_KEY, 300, function (): ?int {
            $id = BranchModel::query()
                ->where('is_active', true)
                ->where('is_default', true)
                ->value('id');

            return $id !== null ? (int) $id : null;
        });
    }

    public static function resolveBranchId(?int $branchId): ?int
    {
        if ($branchId !== null) {
            return $branchId;
        }

        return self::defaultBranchId();
    }

    public static function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
