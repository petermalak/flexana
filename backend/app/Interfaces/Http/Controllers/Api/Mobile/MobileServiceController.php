<?php

namespace App\Interfaces\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\AmeliaServiceModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileServiceController extends Controller
{
    /**
     * Get services for home screen (full details)
     * 
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $services = AmeliaServiceModel::on('wordpress')
            ->where('status', 'visible')
            ->where('show', true)
            ->orderBy('position', 'asc')
            ->get()
            ->map(function ($service) {
                // Extract image from settings if available
                $settings = is_string($service->settings) 
                    ? json_decode($service->settings, true) 
                    : ($service->settings ?? []);
                
                $image = $settings['pictureFullPath'] ?? $settings['pictureThumbPath'] ?? '';
                
                // Extract level from settings or use default
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
     * 
     * @return JsonResponse
     */
    public function simple(): JsonResponse
    {
        $services = AmeliaServiceModel::on('wordpress')
            ->where('status', 'visible')
            ->where('show', true)
            ->orderBy('position', 'asc')
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
