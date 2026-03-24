<?php

namespace App\Interfaces\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\CategoryModel;
use App\Infrastructure\Persistence\Eloquent\StaffModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class MobileInstructorController extends Controller
{
    /**
     * Get instructors for home screen (full details).
     * Query: category (optional) — comma-separated category IDs, slugs (as in GET /api/v1/categories), or names;
     * only instructors with at least one matching category are returned.
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
     * Query: category (optional) — comma-separated category IDs, slugs (as in GET /api/v1/categories), or names;
     * only instructors with at least one matching category are returned.
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
     * Filter query by category from request (query param: category — comma-separated IDs, slugs, or names).
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
            $ids = array_merge($ids, $this->resolveCategoryIdsFromStringToken((string) $token));
        }

        $ids = array_values(array_unique(array_filter($ids)));
        if (count($ids) === 0) {
            return;
        }

        $query->whereHas('categories', fn ($q) => $q->whereIn('categories.id', $ids));
    }

    /**
     * Build match candidates for a category query token (slug variants align with Str::slug on create).
     *
     * @return list<string>
     */
    private function categoryTokenCandidates(string $token): array
    {
        $trimmed = trim($token);
        if ($trimmed === '') {
            return [];
        }

        $lower = strtolower($trimmed);
        $hyphenated = preg_replace('/-+/', '-', str_replace([' ', '_'], '-', $lower));
        $hyphenated = trim((string) $hyphenated, '-');
        $slugified = Str::slug($trimmed);

        $candidates = array_unique(array_values(array_filter([
            $lower,
            $hyphenated !== '' ? $hyphenated : null,
            $slugified !== '' ? strtolower($slugified) : null,
        ])));

        return array_values($candidates);
    }

    /**
     * Resolve active category IDs for one non-numeric token (name or slug, with hyphen/spacing variants).
     *
     * @return list<int>
     */
    private function resolveCategoryIdsFromStringToken(string $token): array
    {
        $candidates = $this->categoryTokenCandidates($token);
        if ($candidates === []) {
            return [];
        }

        $rows = CategoryModel::query()
            ->where('status', true)
            ->where(function ($q) use ($candidates) {
                $q->where(function ($slugQ) use ($candidates) {
                    foreach ($candidates as $c) {
                        $slugQ->orWhereRaw('LOWER(slug) = ?', [$c]);
                    }
                })->orWhere(function ($nameQ) use ($candidates) {
                    foreach ($candidates as $c) {
                        $nameQ->orWhereRaw('LOWER(name) = ?', [$c]);
                    }
                });
            })
            ->pluck('id')
            ->all();

        return array_map(static fn ($id) => (int) $id, $rows);
    }
}
