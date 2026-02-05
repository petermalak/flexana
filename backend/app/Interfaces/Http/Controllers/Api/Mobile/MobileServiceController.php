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
     */
    public function index(Request $request): JsonResponse
    {
        $services = ServiceModel::query()
            ->where('status', 'visible')
            ->where('show', true)
            ->orderBy('position')
            ->orderBy('name')
            ->get()
            ->map(function ($service) {
                $settings = is_array($service->settings) ? $service->settings : [];
                $image = $settings['pictureFullPath'] ?? $settings['pictureThumbPath'] ?? $service->picture_full_path ?? $service->picture_thumb_path ?? '';
                $level = $settings['level'] ?? 'Beginner';
                return [
                    'id' => (string) $service->id,
                    'name' => $service->name ?? '',
                    'level' => $level,
                    'image' => $image,
                    'brief' => $service->description ?? '',
                ];
            })
            ->values()
            ->toArray();

        return response()->json($services);
    }

    /**
     * Get services for schedule screen (simple list)
     */
    public function simple(): JsonResponse
    {
        $services = ServiceModel::query()
            ->where('status', 'visible')
            ->where('show', true)
            ->orderBy('position')
            ->orderBy('name')
            ->get()
            ->map(function ($service) {
                return [
                    'id' => (string) $service->id,
                    'name' => $service->name ?? '',
                ];
            })
            ->values()
            ->toArray();

        return response()->json($services);
    }
}
