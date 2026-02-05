<?php

namespace App\Interfaces\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\StaffModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MobileInstructorController extends Controller
{
    /**
     * Get instructors for home screen (full details)
     */
    public function index(Request $request): JsonResponse
    {
        $instructors = StaffModel::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(function ($instructor) {
                return [
                    'id' => (string) $instructor->id,
                    'name' => $instructor->name ?? '',
                    'image' => '',
                    'position' => $instructor->role ?? '',
                    'brief' => '',
                ];
            })
            ->values()
            ->toArray();

        return response()->json($instructors);
    }

    /**
     * Get instructors for schedule screen (simple list)
     */
    public function simple(): JsonResponse
    {
        $instructors = StaffModel::query()
            ->where('is_active', true)
            ->orderBy('name')
            ->get()
            ->map(function ($instructor) {
                return [
                    'id' => (string) $instructor->id,
                    'name' => $instructor->name ?? '',
                ];
            })
            ->values()
            ->toArray();

        return response()->json($instructors);
    }
}
