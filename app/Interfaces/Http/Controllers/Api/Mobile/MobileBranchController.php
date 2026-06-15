<?php

namespace App\Interfaces\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use Illuminate\Http\JsonResponse;

class MobileBranchController extends Controller
{
    /**
     * Get active branches for the mobile app.
     */
    public function index(): JsonResponse
    {
        $branches = Branch::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Branch $branch): array => [
                'id' => (string) $branch->id,
                'name' => $branch->name ?? '',
                'address' => $branch->address ?? '',
                'phone' => $branch->phone ?? '',
                'isDefault' => (bool) $branch->is_default,
            ])
            ->values()
            ->all();

        return response()->json(['data' => $branches]);
    }
}
