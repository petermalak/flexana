<?php

namespace App\Interfaces\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\BookingModel;
use App\Infrastructure\Persistence\Eloquent\CustomerPackagePurchaseModel;
use App\Infrastructure\Persistence\Eloquent\PackageModel;
use App\Infrastructure\Persistence\Eloquent\PaymentModel;
use App\Infrastructure\Persistence\Eloquent\PromoCodeModel;
use App\Models\Category;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MobilePackageController extends Controller
{
    /**
     * Get package offers grouped by category.
     * Returns a list of categories (from database), each with its related packages.
     * Package-to-category is resolved by: (1) package's services' category_id, (2) fallback: package service_type vs category name.
     * All packages are included; unmatched ones appear under "Uncategorized".
     */
    public function index(Request $request): JsonResponse
    {
        $categories = Category::query()
            ->where('status', true)
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        $packages = PackageModel::query()
            ->with(['services.category', 'classType'])
            ->where('status', 'active')
            ->orderBy('title')
            ->get();

        // Group packages by category: prefer service->category_id, then match service_type to category name
        $packagesByCategoryId = [];
        $uncategorized = [];

        foreach ($packages as $package) {
            $item = $this->mapPackageToItem($package);
            $categoryIds = $this->packageCategoryIds($package);
            $resolvedCategoryId = $this->resolveCategoryForPackage($package, $categoryIds, $categories);

            if ($resolvedCategoryId !== null) {
                $packagesByCategoryId[$resolvedCategoryId][] = $item;
            } else {
                $uncategorized[] = $item;
            }
        }

        $data = $categories->map(function (Category $category) use ($packagesByCategoryId) {
            return [
                'categoryId' => (string) $category->id,
                'categoryName' => $category->name ?? '',
                'slug' => $category->slug ?? '',
                'packages' => array_values($packagesByCategoryId[$category->id] ?? []),
            ];
        })->values()->all();

        if (count($uncategorized) > 0) {
            $data[] = [
                'categoryId' => '',
                'categoryName' => 'Uncategorized',
                'slug' => 'uncategorized',
                'packages' => $uncategorized,
            ];
        }

        return response()->json([
            'data' => $data,
        ]);
    }

    /**
     * Get category IDs from package's services (service->category_id).
     */
    private function packageCategoryIds(PackageModel $package): array
    {
        $ids = [];
        foreach ($package->services as $service) {
            if ($service->category_id !== null) {
                $ids[(int) $service->category_id] = true;
            }
        }

        return array_keys($ids);
    }

    /**
     * Resolve which category this package belongs to.
     * 1) Use first category from package's services (category_id). 2) Else match package service_type to category by slug/name (same rules as services:categorize).
     */
    private function resolveCategoryForPackage(PackageModel $package, array $categoryIdsFromServices, $categories): ?int
    {
        if (count($categoryIdsFromServices) > 0) {
            return (int) $categoryIdsFromServices[0];
        }
        $serviceType = $this->packageServiceType($package);
        if ($serviceType !== null) {
            $category = $this->categoryForCanonicalType($serviceType, $categories);

            return $category ? (int) $category->id : null;
        }

        return null;
    }

    /**
     * Find the DB category for canonical type "Yoga" or "Reformer Pilates".
     * Prefers dedicated categories (yoga-only / pilates-only) so "Yoga & Mat Pilates" doesn't claim both.
     */
    private function categoryForCanonicalType(string $canonicalType, $categories)
    {
        $nameLower = fn ($c) => strtolower($c->name ?? '');
        $slugLower = fn ($c) => strtolower($c->slug ?? '');

        if ($canonicalType === 'Yoga') {
            foreach ($categories as $cat) {
                $nl = $nameLower($cat);
                $sl = $slugLower($cat);
                if ($sl === 'yoga' || (str_contains($nl, 'yoga') && ! str_contains($nl, 'pilates'))) {
                    return $cat;
                }
            }

            return $categories->first(fn ($c) => str_contains($nameLower($c), 'yoga'));
        }

        if ($canonicalType === 'Reformer Pilates') {
            foreach ($categories as $cat) {
                $nl = $nameLower($cat);
                $sl = $slugLower($cat);
                if (in_array($sl, ['reformer-pilates', 'pilates', 'reformer'], true)
                    || str_contains($nl, 'reformer')
                    || (str_contains($nl, 'pilates') && ! str_contains($nl, 'yoga'))) {
                    return $cat;
                }
            }

            return $categories->first(fn ($c) => str_contains($nameLower($c), 'pilates') || str_contains($nameLower($c), 'reformer'));
        }

        return null;
    }

    /**
     * Map a package model to the API item shape.
     */
    private function mapPackageToItem(PackageModel $package): array
    {
        $sessions = (int) ($package->total_sessions ?? 0);
        $expiry = $package->expiry;
        $expirationMonths = 0;
        if ($expiry) {
            $expirationMonths = (int) max(0, Carbon::now()->diffInMonths($expiry, false));
        }

        return [
            'id' => (int) $package->id,
            'name' => $package->title ?? '',
            'price' => (float) ($package->price ?? 0),
            'sessions' => $sessions > 0 ? $sessions : 1,
            'description' => $package->description ?? '',
            'expirationMonths' => $expirationMonths,
            'serviceType' => $this->packageServiceType($package),
            'classFormat' => $package->classType?->name ?? null,
            'packageDuration' => $package->package_duration,
        ];
    }

    /**
     * Derive category/service type (Yoga, Reformer Pilates) from package.
     * Matches if any of: service_type, title, description, or any linked service name/description contain yoga or reformer.
     */
    private function packageServiceType(PackageModel $package): ?string
    {
        $text = $this->packageTextToMatch($package);

        return $this->inferCategoryNameFromText($text);
    }

    /**
     * All package and related service text that may contain category hints.
     */
    private function packageTextToMatch(PackageModel $package): string
    {
        $parts = array_filter([
            $package->service_type ?? '',
            $package->title ?? '',
            $package->description ?? '',
        ]);
        foreach ($package->services as $service) {
            $parts[] = $service->name ?? '';
            $parts[] = $service->description ?? '';
        }

        return implode(' ', $parts);
    }

    /**
     * Infer category name (Yoga | Reformer Pilates) from any text.
     * Uses service-type keywords: yoga styles (vinyasa, hatha, yin, flow, etc.) and pilates (mat pilates, barre, reformer).
     * Reformer Pilates wins if both match.
     */
    private function inferCategoryNameFromText(string $text): ?string
    {
        if ($text === '') {
            return null;
        }
        $lower = strtolower($text);

        $reformerPilatesKeywords = [
            'reformer', 'reform pilates', 'reform pilate', 'mat pilates', 'pilates', 'barre', 'aerial pilates',
        ];
        foreach ($reformerPilatesKeywords as $kw) {
            if (str_contains($lower, $kw)) {
                return 'Reformer Pilates';
            }
        }

        $yogaKeywords = [
            'yoga', 'vinyasa', 'hatha', 'ashtanga', 'restorative', 'yin yoga', 'yin ', 'yin-', 'yin&', 'flow',
            'meditation', 'breathwork', 'mindful', 'destress', 'gentle flow', 'prenatal', 'nidra', 'sukshma',
            'patanjali', 'splits', 'flexibility', 'aerial yoga', 'aerial hoop', 'aerial healing', 'sound meditation',
            'sculpt & yoga', 'hot sculpt', 'sculpt & strengthen', 'healing yoga', 'yin yang', 'bend & extend', 'stress relief',
        ];
        foreach ($yogaKeywords as $kw) {
            if (str_contains($lower, $kw)) {
                return 'Yoga';
            }
        }
        if (preg_match('/\byin\b/', $lower) || str_contains($lower, 'vinyasa') || str_contains($lower, 'hatha')) {
            return 'Yoga';
        }

        return null;
    }

    /**
     * Purchase a package. Uses authenticated customer.
     * Body: { packageId [, promoCode ] }
     */
    public function purchase(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'packageId' => 'required|integer',
            'promoCode' => 'nullable|string|max:64',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $packageId = (int) $validator->validated()['packageId'];
        $promoCode = $validator->validated()['promoCode'] ?? null;

        /** @var Customer $customer */
        $customer = $request->user();

        $package = PackageModel::query()->with('services')->find($packageId);
        if (! $package || $package->status !== 'active') {
            return response()->json([
                'success' => false,
                'message' => 'Package not found or not available',
            ], 404);
        }

        // Use the package's total_sessions (admin-defined), not sum of service pivot quantities
        $totalSessions = (int) ($package->total_sessions ?? 0) ?: 1;
        $price = (float) $package->price;
        $promoRecord = null;

        if ($promoCode) {
            $promoRecord = PromoCodeModel::query()
                ->where('code', $promoCode)
                ->first();
            if ($promoRecord && $promoRecord->isValid()) {
                $price = $price * (1 - (float) $promoRecord->percent_discount / 100);
            } else {
                $promoRecord = null;
            }
        }

        DB::beginTransaction();
        try {
            if ($promoRecord) {
                $promoRecord->increment('used_count');
            }

            $booking = BookingModel::query()->create([
                'customer_id' => $customer->id,
                'package_id' => $package->id,
                'event_id' => null,
                'event_instance_id' => null,
                'appointment_id' => null,
                'service_id' => null,
                'provider_id' => null,
                'status' => 'confirmed',
                'payment_status' => 'pending',
                'party_size' => 1,
                'total_amount' => $price,
                'deposit_amount' => 0,
                'balance_amount' => $price,
                'currency' => 'USD',
                'channel' => 'mobile',
                'booked_at' => now(),
            ]);

            CustomerPackagePurchaseModel::query()->create([
                'customer_id' => $customer->id,
                'package_id' => $package->id,
                'amelia_package_id' => null,
                'total_sessions' => $totalSessions,
                'remaining_sessions' => $totalSessions,
                'purchase_date' => Carbon::now(),
                'status' => 'active',
                'amelia_package_customer_id' => null,
            ]);

            PaymentModel::query()->create([
                'booking_id' => $booking->id,
                'promo_code_id' => $promoRecord?->id,
                'provider' => 'on_site',
                'status' => 'paid',
                'amount' => $price,
                'currency' => 'USD',
                'paid_at' => now(),
            ]);

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => 'Package purchased successfully',
                'data' => [
                    'id' => (string) $booking->id,
                    'packageId' => (string) $package->id,
                    'customerId' => (string) $customer->id,
                ],
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Failed to purchase package',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
