<?php

namespace App\Interfaces\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class BannerController extends Controller
{
    /**
     * Get banners for home screen.
     * Response: [{ "image": string, "URL": string }, ...]
     */
    public function index(): JsonResponse
    {
        // TODO: Replace with actual banner data from database/cms
        $banners = [];

        return response()->json($banners);
    }
}
