<?php

namespace App\Interfaces\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\AmeliaUserModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileInstructorController extends Controller
{
    /**
     * Get instructors for home screen (full details)
     * 
     * @return JsonResponse
     */
    public function index(Request $request): JsonResponse
    {
        $instructors = AmeliaUserModel::on('wordpress')
            ->where('type', 'provider')
            ->where('status', 'visible')
            ->get()
            ->map(function ($instructor) {
                return [
                    'id' => (string) $instructor->id,
                    'name' => trim(($instructor->firstName ?? '') . ' ' . ($instructor->lastName ?? '')),
                    'image' => $instructor->picture ?? '',
                    'position' => $instructor->description ?? '',
                    'brief' => $instructor->note ?? '',
                ];
            })
            ->values()
            ->toArray();

        return response()->json($instructors);
    }

    /**
     * Get instructors for schedule screen (simple list)
     * 
     * @return JsonResponse
     */
    public function simple(): JsonResponse
    {
        $instructors = AmeliaUserModel::on('wordpress')
            ->where('type', 'provider')
            ->where('status', 'visible')
            ->get()
            ->map(function ($instructor) {
                return [
                    'id' => (string) $instructor->id,
                    'name' => trim(($instructor->firstName ?? '') . ' ' . ($instructor->lastName ?? '')),
                ];
            })
            ->values()
            ->toArray();

        return response()->json($instructors);
    }
}
