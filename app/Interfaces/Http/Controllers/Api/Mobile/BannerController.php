<?php

namespace App\Interfaces\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Banner;
use Illuminate\Http\JsonResponse;

class BannerController extends Controller
{
    /**
     * Get banners for home screen.
     * Response: [{ "image": string, "URL": string }, ...]
     */
    public function index(): JsonResponse
    {
        $banners = Banner::query()
            ->where('is_active', true)
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
}
