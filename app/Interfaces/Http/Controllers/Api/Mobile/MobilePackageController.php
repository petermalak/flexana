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
use App\Support\PromoEmailText;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class MobilePackageController extends Controller
{
    /**
     * Get package offers grouped by category.
     * Returns a list of categories (from database), each with its related packages.
     * Package-to-category is resolved by: (1) explicit package service_type (admin Category), (2) keyword inference from text, (3) first linked service's category_id.
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
     * 1) Prefer package service_type (admin-chosen) → map to DB category by slug/name (same rules as services:categorize).
     * 2) If type is not set, fallback to first category_id from package's services.
     */
    private function resolveCategoryForPackage(PackageModel $package, array $categoryIdsFromServices, $categories): ?int
    {
        $serviceType = $this->packageServiceType($package);
        if ($serviceType !== null) {
            $category = $this->categoryForCanonicalType($serviceType, $categories);
            if ($category) {
                return (int) $category->id;
            }
        }

        if (count($categoryIdsFromServices) > 0) {
            return (int) $categoryIdsFromServices[0];
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
        $durationMonths = (int) ($package->package_duration ?? 0);
        if ($durationMonths > 0) {
            $expirationMonths = $durationMonths;
        } elseif ($package->expiry) {
            $expirationMonths = (int) max(0, Carbon::now()->diffInMonths($package->expiry, false));
        } else {
            $expirationMonths = 0;
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
            'packageDuration' => $package->package_duration !== null ? (int) $package->package_duration : null,
        ];
    }

    /**
     * Derive category/service type (Yoga, Reformer Pilates) from package.
     * Admin "Category" is stored as service_type — that wins over text inference so descriptions
     * like "Yoga and Mat pilates" do not override the chosen category (keywords such as "mat pilates"
     * would otherwise map to Reformer Pilates).
     */
    private function packageServiceType(PackageModel $package): ?string
    {
        $explicit = $package->service_type ?? null;
        if ($explicit === 'Yoga' || $explicit === 'Reformer Pilates') {
            return $explicit;
        }

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
        $originalPrice = (float) $package->price;
        $price = $originalPrice;
        $promoRecord = null;

        if ($promoCode) {
            $promoRecord = PromoCodeModel::findByCode($promoCode);
            if ($promoRecord && $promoRecord->isValidForCustomer((int) $customer->id)) {
                $price = $price * (1 - (float) $promoRecord->percent_discount / 100);
            } else {
                $promoRecord = null;
            }
        }

        DB::beginTransaction();
        try {
            if ($promoRecord) {
                if (! $promoRecord->incrementUsageIfAllowed((int) $customer->id)) {
                    throw new \RuntimeException('Promo code usage limit was reached.');
                }
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
                'expires_by_months_only' => true,
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

            try {
                $this->sendPackagePurchaseEmail($customer, $package, $promoRecord, $originalPrice, $price, $totalSessions);
            } catch (\Throwable $e) {
                report($e);
            }

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
            $message = $e->getMessage();
            if (str_contains($message, 'Promo code usage limit')) {
                return response()->json([
                    'success' => false,
                    'message' => 'You have already used this promo code the maximum number of times.',
                ], 400);
            }

            return response()->json([
                'success' => false,
                'message' => 'Failed to purchase package',
                'error' => $message,
            ], 500);
        }
    }

    /**
     * Confirmation email after a package purchase (when the customer has an email).
     */
    private function sendPackagePurchaseEmail(
        Customer $customer,
        PackageModel $package,
        ?PromoCodeModel $promo,
        float $priceBeforeDiscount,
        float $priceAfterDiscount,
        int $totalSessions,
    ): void {
        if (! $customer->email) {
            return;
        }

        $customerName = trim(($customer->first_name ?? '') . ' ' . ($customer->last_name ?? ''));
        $customerName = $customerName !== '' ? $customerName : ($customer->email ?? 'Customer');

        $promoLines = $promo !== null
            ? PromoEmailText::appliedSection($promo, $priceBeforeDiscount, $priceAfterDiscount)
            : '';

        $body = "Thank you for purchasing a package with Flexana!\n\n"
            . "Purchase details\n\n"
            . "* Name: {$customerName}\n\n"
            . "* Email: {$customer->email}\n\n"
            . "* Phone: {$customer->phone}\n\n"
            . "* Package: " . ($package->title ?? 'Package') . "\n\n"
            . "* Sessions included: {$totalSessions}\n\n"
            . $promoLines
            . "You can contact us at +20 122 0221100 if you have any questions.\n\n"
            . "Flexana Team";

        Mail::raw($body, function ($message) use ($customer, $customerName) {
            $message->to($customer->email, $customerName)
                ->cc('Info@flexanaegypt.com')
                ->subject('Your Flexana package purchase confirmation');
        });
    }
}
