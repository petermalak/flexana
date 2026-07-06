<?php

namespace App\Support;

use App\Infrastructure\Persistence\Eloquent\BranchModel;
use Illuminate\Support\Facades\Cache;

final class BranchSettings
{
    private const CACHE_KEY = 'branches.default_id';

    private const CACHE_KEY_META = 'branches.default_meta';

    public static function defaultBranchId(): ?int
    {
        return self::defaultBranchMeta()['id'];
    }

    /**
     * @return array{id: int|null, name: string}
     */
    public static function defaultBranchMeta(): array
    {
        return Cache::remember(self::CACHE_KEY_META, 300, function (): array {
            $branch = BranchModel::query()
                ->where('is_active', true)
                ->where('is_default', true)
                ->first(['id', 'name']);

            if (! $branch) {
                return ['id' => null, 'name' => ''];
            }

            return [
                'id' => (int) $branch->id,
                'name' => (string) ($branch->name ?? ''),
            ];
        });
    }

    public static function resolveBranchId(?int $branchId): ?int
    {
        if ($branchId !== null && (int) $branchId > 0) {
            return (int) $branchId;
        }

        return self::defaultBranchId();
    }

    /**
     * branchId + branchName for session list APIs (null branch_id → default branch).
     *
     * @return array{id: string|null, name: string}
     */
    public static function sessionBranchFields(?int $branchId, ?string $loadedName = null): array
    {
        if ($branchId !== null && (int) $branchId > 0) {
            return [
                'id' => (string) $branchId,
                'name' => (string) ($loadedName ?? ''),
            ];
        }

        $default = self::defaultBranchMeta();

        return [
            'id' => $default['id'] !== null ? (string) $default['id'] : null,
            'name' => $default['name'],
        ];
    }

    public static function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
        Cache::forget(self::CACHE_KEY_META);
    }
}
