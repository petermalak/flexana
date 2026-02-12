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
            $settings = is_array($service->settings) ? $service->settings : [];
            $image = $settings['pictureFullPath'] ?? $settings['pictureThumbPath'] ?? $service->picture_full_path ?? $service->picture_thumb_path ?? '';
            $level = $settings['level'] ?? 'Beginner';

            $serviceData = [
                'id' => (string) $service->id,
                'name' => $service->name ?? '',
                'level' => $level,
                'image' => $image,
                'brief' => $service->description ?? '',
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
}
