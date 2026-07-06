<?php

namespace App\Interfaces\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use Illuminate\Http\JsonResponse;

class MobileBranchController extends Controller
{
    /**
     * Get active branches for the mobile app.
     * Response: [{ id, name, image, address, mapUrl }, ...]
     */
    public function index(): JsonResponse
    {
        $branches = Branch::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Branch $branch): array => [
                'id' => (string) $branch->getKey(),
                'name' => $branch->name ?? '',
                'image' => self::fullImageUrl($branch->image_url),
                'address' => $branch->address ?? '',
                'mapUrl' => $branch->map_url ?? '',
            ])
            ->values()
            ->all();

        return response()->json($branches);
    }

    /**
     * Get active branches (id + name only) for filter/dropdown UI.
     * Response: [{ id, name }, ...]
     */
    public function simple(): JsonResponse
    {
        $branches = Branch::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Branch $branch): array => [
                'id' => (string) $branch->id,
                'name' => $branch->name ?? '',
            ])
            ->values()
            ->all();

        return response()->json($branches);
    }

    private static function fullImageUrl(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return url('serve-storage.php') . '?path=' . rawurlencode($path);
    }
}
