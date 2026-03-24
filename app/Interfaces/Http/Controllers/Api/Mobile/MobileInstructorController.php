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
     * Query: category (optional) — comma-separated category IDs, slugs (as in GET /api/v1/categories), or names.
     * Filtering uses the same instructor categories as Filament (staff “Categories”, category_staff pivot), not service categories.
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
     * Query: category (optional) — comma-separated category IDs, slugs (as in GET /api/v1/categories), or names.
     * Filtering uses the same instructor categories as Filament (staff “Categories”, category_staff pivot), not service categories.
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
     * Filter staff by categories assigned on the instructor record (category_staff), matching resolved category IDs.
     */
    private function applyCategoryFilter($query, Request $request): void
    {
        $param = $request->input('category');
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
            $ids = array_merge($ids, $this->resolveCategoryIdsForInstructorFilter((string) $token));
        }

        $ids = array_values(array_unique(array_filter($ids)));

        if (count($ids) === 0) {
            $query->whereRaw('0 = 1');

            return;
        }

        $query->whereHas(
            'categories',
            fn ($q) => $q->whereIn('categories.id', $ids),
        );
    }

    /**
     * Resolve one filter token to category IDs. Mobile sends the category slug from GET /api/v1/categories — match that first.
     *
     * @return list<int>
     */
    private function resolveCategoryIdsForInstructorFilter(string $token): array
    {
        $t = trim($token);
        if ($t === '') {
            return [];
        }

        // 1) Slug: same field as `slug` in categories API (case-insensitive, trimmed).
        $bySlug = CategoryModel::query()
            ->where('status', true)
            ->whereNotNull('slug')
            ->where('slug', '!=', '')
            ->whereRaw('LOWER(TRIM(slug)) = LOWER(?)', [$t])
            ->pluck('id')
            ->all();

        if ($bySlug !== []) {
            return array_map(static fn ($id) => (int) $id, $bySlug);
        }

        // 2) Exact display name (case-insensitive).
        $byName = CategoryModel::query()
            ->where('status', true)
            ->whereRaw('LOWER(TRIM(name)) = LOWER(?)', [$t])
            ->pluck('id')
            ->all();

        if ($byName !== []) {
            return array_map(static fn ($id) => (int) $id, $byName);
        }

        // 3) Hyphen / slugify variants (e.g. legacy tokens).
        $fromVariants = $this->resolveCategoryIdsFromStringToken($t);
        if ($fromVariants !== []) {
            return $fromVariants;
        }

        // 4) Schedule-tab shortcuts: yoga, reformer, reformer-pilates, etc.
        return $this->resolveCategoryIdsFromCanonicalScheduleToken($t);
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
                        $slugQ->orWhereRaw('LOWER(TRIM(slug)) = ?', [$c]);
                    }
                })->orWhere(function ($nameQ) use ($candidates) {
                    foreach ($candidates as $c) {
                        $nameQ->orWhereRaw('LOWER(TRIM(name)) = ?', [$c]);
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
        $c = strtolower(trim(str_replace(['-', '_'], ' ', $category)));
        $c = preg_replace('/\s+/', ' ', $c) ?? '';
        if (in_array($c, ['yoga'], true)) {
            return 'Yoga';
        }
        if (in_array($c, [
            'reformer', 'reformers', 'reformer pilates', 'reformers pilates', 'reform pilates',
            'reformer pilate', 'reform pilate',
        ], true)) {
            return 'Reformer Pilates';
        }

        // e.g. "reformerpilates" if something strips spaces
        if (in_array(preg_replace('/\s+/', '', $c) ?: '', ['reformerpilates', 'reformerspilates'], true)) {
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
            } elseif (in_array($sl, ['reformer-pilates', 'reformers-pilates', 'pilates', 'reformer', 'reformers'], true)
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
                $nl = $nameLower($cat);
                $sl = $slugLower($cat);
                if (str_contains($nl, 'reformer') || in_array($sl, ['reformer-pilates', 'reformers-pilates', 'pilates', 'reformer', 'reformers'], true)) {
                    $reformerIds[] = $cat->id;
                    break;
                }
            }
        }
        if (count($reformerIds) === 0) {
            foreach ($categories as $cat) {
                $nl = $nameLower($cat);
                if (str_contains($nl, 'pilates') && ! str_contains($nl, 'yoga')) {
                    $reformerIds[] = $cat->id;
                    break;
                }
            }
        }

        $ids = $canonicalType === 'Yoga' ? $yogaIds : $reformerIds;

        return array_map(static fn ($id) => (int) $id, $ids);
    }
}
