<?php

namespace App\Interfaces\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\ServiceModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileServiceController extends Controller
{
    /**
     * Get services for home screen (full details)
     * Returns services grouped by two primary categories: Yoga and Reformer Pilates
     */
    public function index(Request $request): JsonResponse
    {
        $branchId = $request->filled('branchId') ? (int) $request->query('branchId') : null;

        $services = ServiceModel::query()
            ->where('status', 'visible')
            ->where('show', true)
            ->when($branchId, fn ($query) => $query->whereHas(
                'branches',
                fn ($branchQuery) => $branchQuery->whereKey($branchId),
            ))
            ->with($branchId ? ['branches' => fn ($query) => $query->whereKey($branchId)] : 'branches')
            ->where(function ($query) {
                $query->where('name', 'LIKE', '%Yoga%')
                    ->orWhere('name', 'LIKE', '%Reformer Pilates%')
                    ->orWhere('name', 'LIKE', '%Reform Pilates%');
            })
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        // Group services by category
        $yogaServices = [];
        $reformerPilatesServices = [];

        foreach ($services as $service) {
            $settings = is_array($service->settings) ? $service->settings : [];
            $image = $settings['pictureFullPath'] ?? $settings['pictureThumbPath'] ?? $service->picture_full_path ?? $service->picture_thumb_path ?? '';
            $level = $settings['level'] ?? 'Beginner';

            $pictureFull = $service->picture_full_path ?? null;
            $pictureThumb = $service->picture_thumb_path ?? null;
            $galleryRaw = is_array($service->gallery) ? $service->gallery : [];

            $serviceData = [
                'id' => (string) $service->id,
                'name' => $service->name ?? '',
                'level' => $level,
                'image' => self::fullImageUrl($image),
                'brief' => $service->description ?? '',
                'description' => $service->description ?? '',
                'duration' => (int) ($service->duration ?? 0),
                'price' => $service->priceForBranch($branchId),
                'minCapacity' => (int) ($service->min_capacity ?? 1),
                'maxCapacity' => (int) ($service->max_capacity ?? 1),
                'colorHex' => $service->color_hex ?? null,
                'pictureFullPath' => self::fullImageUrl($pictureFull),
                'pictureThumbPath' => self::fullImageUrl($pictureThumb),
                'gallery' => array_values(array_map([self::class, 'fullImageUrl'], $galleryRaw)),
                'timeBefore' => (int) ($service->time_before ?? 0),
                'timeAfter' => (int) ($service->time_after ?? 0),
                'categoryId' => $service->category_id ?? null,
                'position' => (int) ($service->position ?? 0),
                'deposit' => (float) ($service->deposit ?? 0),
                'depositPayment' => $service->deposit_payment ?? null,
                'status' => $service->status ?? null,
                'extras' => is_array($service->extras) ? $service->extras : [],
                'settings' => $settings,
            ];

            // Determine category based on service name
            $serviceName = strtolower($service->name ?? '');
            if (str_contains($serviceName, 'reformer pilates') || str_contains($serviceName, 'reform pilates')) {
                $reformerPilatesServices[] = $serviceData;
            } elseif (str_contains($serviceName, 'yoga')) {
                $yogaServices[] = $serviceData;
            }
        }

        // Build categories array
        $categories = [];

        if (!empty($yogaServices)) {
            $categories[] = [
                'category' => 'Yoga',
                'services' => $yogaServices,
            ];
        }

        if (!empty($reformerPilatesServices)) {
            $categories[] = [
                'category' => 'Reformer Pilates',
                'services' => $reformerPilatesServices,
            ];
        }

        return response()->json($categories);
    }

    /**
     * Get services for schedule screen (simple list)
     * Returns services grouped by two primary categories: Yoga and Reformer Pilates
     */
    public function simple(): JsonResponse
    {
        $services = ServiceModel::query()
            ->where('status', 'visible')
            ->where('show', true)
            ->where(function ($query) {
                $query->where('name', 'LIKE', '%Yoga%')
                    ->orWhere('name', 'LIKE', '%Reformer Pilates%')
                    ->orWhere('name', 'LIKE', '%Reform Pilates%');
            })
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        // Group services by category
        $yogaServices = [];
        $reformerPilatesServices = [];

        foreach ($services as $service) {
            $serviceData = [
                'id' => (string) $service->id,
                'name' => $service->name ?? '',
            ];

            // Determine category based on service name
            $serviceName = strtolower($service->name ?? '');
            if (str_contains($serviceName, 'reformer pilates') || str_contains($serviceName, 'reform pilates')) {
                $reformerPilatesServices[] = $serviceData;
            } elseif (str_contains($serviceName, 'yoga')) {
                $yogaServices[] = $serviceData;
            }
        }

        // Build categories array
        $categories = [];

        if (!empty($yogaServices)) {
            $categories[] = [
                'category' => 'Yoga & Mat Pilates',
                'services' => $yogaServices,
            ];
        }

        if (!empty($reformerPilatesServices)) {
            $categories[] = [
                'category' => 'Reformer Pilates',
                'services' => $reformerPilatesServices,
            ];
        }

        // Main service categories for filter UI (id = slug for filtering)
        $mainCategories = [];
        if (!empty($yogaServices)) {
            $mainCategories[] = ['id' => 'yoga', 'name' => 'Yoga & Mat Pilates'];
        }
        if (!empty($reformerPilatesServices)) {
            $mainCategories[] = ['id' => 'reformer-pilates', 'name' => 'Reformer Pilates'];
        }

        return response()->json([
            'categories' => $mainCategories,
            'data' => $categories,
        ]);
    }

    /**
     * Return full URL for an image path (storage path or existing URL).
     */
    private static function fullImageUrl(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }
        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }
        return asset('storage/'.ltrim($path, '/'));
    }
}
