<?php

namespace App\Interfaces\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BannerController extends Controller
{
    /**
     * Get banners for home screen.
     * Optional query param: category=home|popup (defaults to home).
     * Response: [{ "title": string, "image": string, "URL": string }, ...]
     */
    public function index(Request $request): JsonResponse
    {
        $category = $request->query('category', Banner::CATEGORY_HOME);

        $banners = Banner::query()
            ->where('is_active', true)
            ->where('category', $category)
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->get()
            ->map(function (Banner $banner): array {
                $imagePath = $banner->image_url;
                $imageUrl = $imagePath
                    ? (str_starts_with($imagePath, 'http') ? $imagePath : url('serve-storage.php') . '?path=' . rawurlencode($imagePath))
                    : null;

                return [
                    'title' => $banner->title,
                    'image' => $imageUrl,
                    'URL' => $banner->link_url,
                ];
            })
            ->values()
            ->all();

        return response()->json($banners);
    }

    /**
     * Get active banners (id + name only) for filter/dropdown UI.
     * Optional query param: category=home|popup (defaults to home).
     * Response: [{ id, name }, ...]
     */
    public function simple(Request $request): JsonResponse
    {
        $category = $request->query('category', Banner::CATEGORY_HOME);

        $banners = Banner::query()
            ->where('is_active', true)
            ->where('category', $category)
            ->orderBy('sort_order')
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (Banner $banner): array => [
                'id' => (string) $banner->id,
                'name' => $banner->title ?? '',
            ])
            ->values()
            ->all();

        return response()->json($banners);
    }
}
