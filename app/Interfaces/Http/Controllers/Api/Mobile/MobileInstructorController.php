<?php

namespace App\Interfaces\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\CategoryModel;
use App\Infrastructure\Persistence\Eloquent\StaffModel;
use App\Models\Category;
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
            $t = (string) $token;
            $resolved = $this->resolveCategoryIdsFromStringToken($t);
            if ($resolved === []) {
                $resolved = $this->resolveCategoryIdsFromCanonicalScheduleToken($t);
            }
            $ids = array_merge($ids, $resolved);
        }

        $ids = array_values(array_unique(array_filter($ids)));
        if (count($ids) === 0) {
            // Param was provided but nothing matched — do not return unfiltered instructors.
            $query->whereRaw('0 = 1');

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

    /**
     * Same shortcuts as sessions API (?category=yoga | reformer): map tab label to DB category IDs.
     *
     * @return list<int>
     */
    private function resolveCategoryIdsFromCanonicalScheduleToken(string $token): array
    {
        $canonical = $this->normalizeScheduleCategoryParam($token);
        if ($canonical === null) {
            return [];
        }

        return $this->scheduleTabCategoryIds($canonical);
    }

    /**
     * Align with {@see MobileSessionController::normalizeCategoryFilter}.
     */
    private function normalizeScheduleCategoryParam(string $category): ?string
    {
        $c = strtolower(trim(str_replace('-', ' ', $category)));
        if (in_array($c, ['yoga'], true)) {
            return 'Yoga';
        }
        if (in_array($c, ['reformer', 'reformer pilates', 'reform pilates'], true)) {
            return 'Reformer Pilates';
        }

        return null;
    }

    /**
     * Category ID sets for Yoga vs Reformer schedule tabs — same rules as
     * {@see MobileSessionController::getCategoryFilterData} (IDs only).
     *
     * @return list<int>
     */
    private function scheduleTabCategoryIds(string $canonicalType): array
    {
        $categories = Category::query()
            ->where('status', true)
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        $nameLower = fn ($c) => strtolower($c->name ?? '');
        $slugLower = fn ($c) => strtolower($c->slug ?? '');

        $yogaIds = [];
        $reformerIds = [];
        foreach ($categories as $cat) {
            $nl = $nameLower($cat);
            $sl = $slugLower($cat);
            if ($sl === 'yoga' || (str_contains($nl, 'yoga') && ! str_contains($nl, 'pilates'))) {
                $yogaIds[] = $cat->id;
            } elseif (in_array($sl, ['reformer-pilates', 'pilates', 'reformer'], true)
                || str_contains($nl, 'reformer')
                || (str_contains($nl, 'pilates') && ! str_contains($nl, 'yoga'))) {
                $reformerIds[] = $cat->id;
            }
        }
        if (count($yogaIds) === 0) {
            foreach ($categories as $cat) {
                if (str_contains($nameLower($cat), 'yoga')) {
                    $yogaIds[] = $cat->id;
                    break;
                }
            }
        }
        if (count($reformerIds) === 0) {
            foreach ($categories as $cat) {
                if (str_contains($nameLower($cat), 'pilates') || str_contains($nameLower($cat), 'reformer')) {
                    $reformerIds[] = $cat->id;
                    break;
                }
            }
        }

        $ids = $canonicalType === 'Yoga' ? $yogaIds : $reformerIds;

        return array_map(static fn ($id) => (int) $id, $ids);
    }
}
