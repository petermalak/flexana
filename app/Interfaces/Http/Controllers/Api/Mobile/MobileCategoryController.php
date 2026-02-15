<?php

namespace App\Interfaces\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Category;
use Illuminate\Http\JsonResponse;

class MobileCategoryController extends Controller
{
    /**
     * Get categories for the mobile app (with full image URLs).
     * Returns active categories ordered by position.
     */
    public function index(): JsonResponse
    {
        $categories = Category::query()
            ->where('status', true)
            ->orderBy('position')
            ->orderBy('name')
            ->get()
            ->map(fn (Category $category): array => [
                'id' => (string) $category->id,
                'name' => $category->name ?? '',
                'slug' => $category->slug ?? '',
                'type' => $category->type ?? 'service',
                'description' => $category->description ?? '',
                'image' => self::fullImageUrl($category->image),
                'position' => (int) ($category->position ?? 0),
            ])
            ->values()
            ->all();

        return response()->json(['data' => $categories]);
    }

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
