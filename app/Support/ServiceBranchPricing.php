<?php

namespace App\Support;

use App\Infrastructure\Persistence\Eloquent\BranchModel;
use App\Infrastructure\Persistence\Eloquent\ServiceModel;
use Illuminate\Support\Collection;

final class ServiceBranchPricing
{
    /**
     * @return list<array{branch_id: int, branch_name: string, price: float|null}>
     */
    public static function formRows(ServiceModel $service): array
    {
        $service->loadMissing('branches');

        return self::rowsForSelectedBranches(
            $service->branches->pluck('id')->all(),
            (float) ($service->price ?? 0),
            $service->branches
                ->map(fn (BranchModel $branch): array => [
                    'branch_id' => (string) $branch->id,
                    'price' => $branch->pivot->price !== null
                        ? (float) $branch->pivot->price
                        : (float) ($service->price ?? 0),
                ])
                ->values()
                ->all(),
        );
    }

    /**
     * @param  list<int|string>  $branchIds
     * @param  list<array{branch_id?: int|string|null, price?: float|int|string|null}>  $existingRows
     * @return list<array{branch_id: string, price: float}>
     */
    public static function rowsForSelectedBranches(array $branchIds, float $defaultPrice, array $existingRows = []): array
    {
        $existingByBranch = collect($existingRows)
            ->filter(fn (array $row): bool => ! empty($row['branch_id']))
            ->keyBy(fn (array $row): int => (int) $row['branch_id']);

        return collect($branchIds)
            ->map(fn ($branchId): array => [
                'branch_id' => (string) $branchId,
                'price' => (float) (
                    $existingByBranch->get((int) $branchId)['price']
                    ?? $defaultPrice
                ),
            ])
            ->values()
            ->all();
    }

    /**
     * @param  list<array{branch_id?: int|null, price?: float|int|string|null}>  $branchPrices
     */
    public static function applyPivotPrices(ServiceModel $service, array $branchPrices): void
    {
        if ($branchPrices === []) {
            return;
        }

        $service->loadMissing('branches');

        $pricesByBranch = collect($branchPrices)
            ->filter(fn (array $row): bool => ! empty($row['branch_id']))
            ->keyBy(fn (array $row): int => (int) $row['branch_id']);

        $sync = $service->branches
            ->mapWithKeys(function (BranchModel $branch) use ($pricesByBranch, $service): array {
                $row = $pricesByBranch->get((int) $branch->id);
                $price = $row['price'] ?? null;

                return [
                    (int) $branch->id => [
                        'price' => $price !== null && $price !== ''
                            ? (float) $price
                            : (float) ($service->price ?? 0),
                    ],
                ];
            })
            ->all();

        if ($sync !== []) {
            $service->branches()->sync($sync);
        }
    }

    /**
     * After Filament syncs branch IDs, ensure each pivot row has a price.
     *
     * @param  list<int|string>  $branchIds
     */
    public static function ensurePivotPricesForBranches(ServiceModel $service, array $branchIds): void
    {
        if ($branchIds === []) {
            return;
        }

        $defaultPrice = (float) ($service->price ?? 0);
        $service->loadMissing('branches');

        $sync = Collection::make($branchIds)
            ->mapWithKeys(function ($branchId) use ($service, $defaultPrice): array {
                $branchId = (int) $branchId;
                $existing = $service->branches->firstWhere('id', $branchId);
                $price = $existing?->pivot?->price;

                return [
                    $branchId => [
                        'price' => $price !== null ? (float) $price : $defaultPrice,
                    ],
                ];
            })
            ->all();

        $service->branches()->sync($sync);
    }
}
