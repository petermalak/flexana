<?php

namespace App\Interfaces\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\CategoryModel;
use App\Infrastructure\Persistence\Eloquent\StaffModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class MobileInstructorController extends Controller
{
    /**
     * Get instructors for home screen (full details).
     * Query: category (optional) — comma-separated category IDs or names (or slugs); only instructors with at least one matching category are returned.
     */
    public function index(Request $request): JsonResponse
    {
        $query = StaffModel::query()
            ->with('categories')
            ->where('is_active', true);

        $this->applyCategoryFilter($query, $request);

        $instructors = $query->orderBy('name')->get()
            ->map(function ($instructor) {
                $imageUrl = $instructor->photo_path
                    ? url('serve-storage.php') . '?path=' . rawurlencode(ltrim($instructor->photo_path, '/'))
                    : '';

                $categories = $instructor->categories->map(fn ($c) => ['id' => (string) $c->id, 'name' => $c->name ?? ''])->values()->toArray();

                return [
                    'id' => (string) $instructor->id,
                    'name' => $instructor->name ?? '',
                    'image' => $imageUrl,
                    'position' => $instructor->role ?? '',
                    'brief' => '',
                    'categories' => $categories,
                ];
            })
            ->values()
            ->toArray();

        return response()->json($instructors);
    }

    /**
     * Get instructors for schedule screen (simple list).
     * Query: category (optional) — comma-separated category IDs or names (or slugs); only instructors with at least one matching category are returned.
     */
    public function simple(Request $request): JsonResponse
    {
        $query = StaffModel::query()->where('is_active', true);

        $this->applyCategoryFilter($query, $request);

        $instructors = $query->orderBy('name')->get()
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

    /**
     * Filter query by category IDs or names from request (query param: category — comma-separated IDs or names/slugs).
     */
    private function applyCategoryFilter($query, Request $request): void
    {
        $param = $request->query('category');
        if ($param === null || $param === '') {
            return;
        }

        $tokens = Collection::make(explode(',', (string) $param))->map(fn ($v) => trim($v))->filter(fn ($v) => $v !== '')->unique()->values();

        $ids = [];
        foreach ($tokens as $token) {
            if (is_numeric($token) && (int) $token > 0) {
                $ids[] = (int) $token;
                continue;
            }
            $found = CategoryModel::query()
                ->where('status', true)
                ->where(function ($q) use ($token) {
                    $lower = strtolower($token);
                    $q->whereRaw('LOWER(name) = ?', [$lower])
                        ->orWhereRaw('LOWER(slug) = ?', [$lower]);
                })
                ->pluck('id')
                ->all();
            $ids = array_merge($ids, $found);
        }

        $ids = array_values(array_unique(array_filter($ids)));
        if (count($ids) === 0) {
            return;
        }

        $query->whereHas('categories', fn ($q) => $q->whereIn('categories.id', $ids));
    }
}
