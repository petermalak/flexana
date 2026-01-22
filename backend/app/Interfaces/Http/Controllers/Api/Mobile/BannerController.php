<?php

namespace App\Interfaces\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class BannerController extends Controller
{
    /**
     * Get banners for home screen
     * 
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        // TODO: Replace with actual banner data from database
        // For now, returning empty array as placeholder
        $banners = [
            // Example structure:
            // [
            //     'image' => 'https://example.com/banner1.jpg',
            //     'URL' => 'https://example.com/promo1',
            // ],
        ];

        return response()->json($banners);
    }
}
