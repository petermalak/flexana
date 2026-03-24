<?php

namespace App\Interfaces\Http\Controllers\Api\Mobile;

use App\Console\Commands\CategorizeServicesCommand;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\AppointmentModel;
use App\Infrastructure\Persistence\Eloquent\CustomerPackagePurchaseModel;
use App\Infrastructure\Persistence\Eloquent\ServiceModel;
use App\Models\Category;
use App\Support\ApiDateTime;
use App\Support\PackagePurchaseExpiry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

class MobileSessionController extends Controller
{
    /**
     * Get sessions (appointments) with optional pagination.
     * Query params: date, category (Yoga | Reformer Pilates | yoga | reformer | reformer-pilates), instructorID, per_page (default 15), page
     */
    public function index(Request $request): JsonResponse
    {
        $date = $request->query('date');
        $category = $request->query('category');
        $instructorID = $request->query('instructorID');
        $requestedPerPage = $request->query('per_page');
        $requestedPage = $request->query('page');
        $shouldPaginate = $requestedPerPage !== null || $requestedPage !== null;

        $scheduleTz = (string) config('sessions.schedule_timezone', config('app.timezone'));
        $upcomingCutoff = Carbon::now($scheduleTz);

        $query = AppointmentModel::query()
            ->with(['service.category', 'provider', 'bookings'])
            ->where('status', 'approved')
            // Only upcoming sessions — use schedule TZ so SQL + PHP match studio clocks / stored datetimes
            ->where('booking_start', '>', $upcomingCutoff);

        if ($date) {
            try {
                $dateCarbon = Carbon::parse($date, $scheduleTz);
                // Use whereBetween to handle timezone correctly - filter for the entire day
                $startOfDay = $dateCarbon->copy()->startOfDay();
                $endOfDay = $dateCarbon->copy()->endOfDay();
                $query->whereBetween('booking_start', [$startOfDay, $endOfDay]);
            } catch (\Exception $e) {
                // Invalid date format - ignore the filter
                Log::warning('Invalid date filter in sessions API: ' . $date);
            }
        }
        if ($category !== null && $category !== '') {
            $canonicalType = $this->normalizeCategoryFilter($category);
            if ($canonicalType !== null) {
                $this->applyCategoryFilter($query, $canonicalType);
            }
        }
        if ($instructorID) {
            $query->where('provider_id', $instructorID);
        }

        $query->orderBy('booking_start');

        if ($shouldPaginate) {
            $perPage = max(1, min(100, (int) ($requestedPerPage ?: 15)));
            $appointments = $query->paginate($perPage);
            $appointmentCollection = $appointments->getCollection();
            $meta = [
                'current_page' => $appointments->currentPage(),
                'last_page' => $appointments->lastPage(),
                'per_page' => $appointments->perPage(),
                'total' => $appointments->total(),
            ];
        } else {
            $appointmentCollection = $query->get();
            $total = $appointmentCollection->count();
            $meta = [
                'current_page' => 1,
                'last_page' => 1,
                'per_page' => $total,
                'total' => $total,
            ];
        }

        $customerId = $request->user()?->id;

        $items = $appointmentCollection->map(function ($appointment) use ($customerId, $scheduleTz, $upcomingCutoff) {
            $service = $appointment->service;
            $provider = $appointment->provider;
            $approvedBookings = $appointment->bookings->where('status', 'confirmed');
            $totalPersons = $approvedBookings->sum('party_size');
            $maxCapacity = $service ? ($service->max_capacity ?? 1) : 1;
            $isFull = $totalPersons >= $maxCapacity;
            $myBooking = $customerId
                ? $approvedBookings->where('customer_id', $customerId)->first()
                : null;
            $isBooked = $myBooking !== null;
            $minutesBeforeCancellation = (int) ($service->time_before ?? 0);
            $canCancelUntil = $myBooking
                ? Carbon::parse($appointment->booking_start, $scheduleTz)->subMinutes($minutesBeforeCancellation)
                : null;
            $canCancel = $isBooked && $canCancelUntil && Carbon::now($scheduleTz)->lte($canCancelUntil);
            $startInstant = $appointment->booking_start instanceof Carbon
                ? $appointment->booking_start->copy()
                : Carbon::parse($appointment->booking_start, $scheduleTz);
            $canBook = ! $isFull && $startInstant->gt($upcomingCutoff);

            $serviceType = $this->sessionCategoryFromService($service);

            $willPay = true;
            if ($customerId && $service instanceof ServiceModel) {
                $sessionCategory = $this->sessionCategoryFromService($service);
                $validPurchase = $this->findValidPurchaseForCategory((int) $customerId, $sessionCategory, 1);
                $willPay = $validPurchase === null;
            }

            return [
                'id' => (string) $appointment->id,
                'bookingId' => $myBooking ? (string) $myBooking->id : null,
                'instructor' => $provider ? $provider->name : '',
                'service' => $service ? $service->name : '',
                'serviceType' => $serviceType,
                'price' => $service ? (float) ($service->price ?? 0) : 0.0,
                'date' => ApiDateTime::toBusinessIso8601($appointment->booking_start),
                'dateUtc' => ApiDateTime::toUtcIso8601($appointment->booking_start),
                'isBooked' => $isBooked,
                'isFull' => $isFull,
                'canCancel' => $canCancel,
                'canBook' => $canBook,
                'willPay' => $willPay,
                'minutesBeforeCancellation' => $minutesBeforeCancellation,
            ];
        });

        return response()->json([
            'data' => $items->values()->toArray(),
            'meta' => $meta,
        ]);
    }

    /**
     * Remove appointments whose start is not strictly after the cutoff (defence in depth).
     *
     * @param  \Illuminate\Support\Collection<int, \App\Infrastructure\Persistence\Eloquent\AppointmentModel>  $appointments
     * @return \Illuminate\Support\Collection<int, \App\Infrastructure\Persistence\Eloquent\AppointmentModel>
     */
    private function filterUpcomingAppointments($appointments, Carbon $cutoff)
    {
        return $appointments->filter(function ($appointment) use ($cutoff) {
            if (! $appointment->booking_start) {
                return false;
            }
            $start = $appointment->booking_start instanceof Carbon
                ? $appointment->booking_start->copy()
                : Carbon::parse($appointment->booking_start);

            return $start->gt($cutoff);
        })->values();
    }

    /**
     * Normalize category query param to canonical "Yoga" or "Reformer Pilates".
     */
    private function normalizeCategoryFilter(string $category): ?string
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
     * Filter appointments by category: service.category_id in type's category IDs, or service name/description matches type keywords.
     */
    private function applyCategoryFilter($query, string $canonicalType): void
    {
        [$yogaCategoryIds, $yogaRegexpPattern] = $this->getCategoryFilterData('Yoga');
        [$reformerCategoryIds, $reformerRegexpPattern] = $this->getCategoryFilterData('Reformer Pilates');

        $serviceTextExpr = 'LOWER(CONCAT(COALESCE(name,""), " ", COALESCE(description,"")))';

        if ($canonicalType === 'Reformer Pilates') {
            $query->whereHas('service', function ($q) use ($reformerCategoryIds, $reformerRegexpPattern, $serviceTextExpr) {
                $q->where(function ($q2) use ($reformerCategoryIds, $reformerRegexpPattern, $serviceTextExpr) {
                    if (count($reformerCategoryIds) > 0) {
                        $q2->whereIn('category_id', $reformerCategoryIds);
                    }
                    if ($reformerRegexpPattern !== '') {
                        $q2->orWhere(function ($qRegex) use ($serviceTextExpr, $reformerRegexpPattern) {
                            $qRegex->whereNull('category_id')
                                ->whereRaw("{$serviceTextExpr} REGEXP ?", [$reformerRegexpPattern]);
                        });
                    }
                    if (count($reformerCategoryIds) === 0 && $reformerRegexpPattern === '') {
                        $q2->whereRaw('1 = 0');
                    }
                });
            });

            return;
        }

        // Yoga includes explicit Yoga matches + any non-Reformer sessions.
        // This ensures Yoga/Reformer split covers all sessions when only these two categories are used.
        $query->whereHas('service', function ($q) use (
            $yogaCategoryIds,
            $yogaRegexpPattern,
            $reformerCategoryIds,
            $reformerRegexpPattern,
            $serviceTextExpr
        ) {
            $q->where(function ($q2) use (
                $yogaCategoryIds,
                $yogaRegexpPattern,
                $reformerCategoryIds,
                $reformerRegexpPattern,
                $serviceTextExpr
            ) {
                // Explicit Yoga match
                $q2->where(function ($qy) use ($yogaCategoryIds, $yogaRegexpPattern, $serviceTextExpr) {
                    if (count($yogaCategoryIds) > 0) {
                        $qy->whereIn('category_id', $yogaCategoryIds);
                    }
                    if ($yogaRegexpPattern !== '') {
                        $qy->orWhere(function ($qRegex) use ($serviceTextExpr, $yogaRegexpPattern) {
                            $qRegex->whereNull('category_id')
                                ->whereRaw("{$serviceTextExpr} REGEXP ?", [$yogaRegexpPattern]);
                        });
                    }
                });

                // Fallback: anything not identified as Reformer
                $q2->orWhere(function ($qn) use ($reformerCategoryIds, $reformerRegexpPattern, $serviceTextExpr) {
                    if (count($reformerCategoryIds) > 0) {
                        $qn->where(function ($qCat) use ($reformerCategoryIds) {
                            $qCat->whereNull('category_id')
                                ->orWhereNotIn('category_id', $reformerCategoryIds);
                        });
                    }
                    if ($reformerRegexpPattern !== '') {
                        $qn->whereRaw("{$serviceTextExpr} NOT REGEXP ?", [$reformerRegexpPattern]);
                    }
                });
            });
        });
    }

    /**
     * Get category IDs and regexp pattern for Yoga or Reformer Pilates (same logic as services:categorize).
     */
    private function getCategoryFilterData(string $canonicalType): array
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
        $pattern = $canonicalType === 'Yoga' ? self::yogaRegexpPattern() : self::reformerRegexpPattern();

        return [$ids, $pattern];
    }

    private static function yogaRegexpPattern(): string
    {
        $keywords = [
            'yoga', 'vinyasa', 'hatha', 'ashtanga', 'restorative', 'yin yoga', 'yin ', 'yin-', 'yin&', 'flow',
            'meditation', 'breathwork', 'mindful', 'destress', 'gentle flow', 'prenatal', 'nidra', 'sukshma',
            'patanjali', 'splits', 'flexibility', 'aerial yoga', 'aerial hoop', 'aerial healing', 'sound meditation',
            'sculpt & yoga', 'hot sculpt', 'sculpt & strengthen', 'healing yoga', 'yin yang', 'bend & extend', 'stress relief',
        ];

        return implode('|', array_map('preg_quote', $keywords));
    }

    private static function reformerRegexpPattern(): string
    {
        $keywords = [
            'reformer', 'reform pilates', 'reform pilate', 'mat pilates', 'pilates', 'barre', 'aerial pilates',
        ];

        return implode('|', array_map('preg_quote', $keywords));
    }

    /**
     * Session category (Yoga / Reformer Pilates) from service name. Used to match package category.
     * MUST stay in sync with MobileBookingController::sessionCategoryFromService().
     */
    private function sessionCategoryFromService(?ServiceModel $service): ?string
    {
        if (! $service) {
            return null;
        }
        $category = $service->relationLoaded('category') ? $service->category : null;
        if ($category) {
            $name = strtolower((string) ($category->name ?? ''));
            $slug = strtolower((string) ($category->slug ?? ''));
            if ($slug === 'yoga' || (str_contains($name, 'yoga') && ! str_contains($name, 'pilates'))) {
                return 'Yoga';
            }
            if (in_array($slug, ['reformer-pilates', 'pilates', 'reformer'], true)
                || str_contains($name, 'reformer')
                || (str_contains($name, 'pilates') && ! str_contains($name, 'yoga'))) {
                return 'Reformer Pilates';
            }
        }
        if (! $service->name) {
            return null;
        }
        $serviceText = trim(($service->name ?? '') . ' ' . ($service->description ?? ''));

        return CategorizeServicesCommand::inferCategoryNameFromText($serviceText) ?? 'Yoga';
    }

    /**
     * Find an active customer package purchase with matching category and enough remaining sessions.
     * Prefers most recently purchased. Excludes expired (by package_duration + purchase_date).
     * MUST stay in sync with MobileBookingController::findValidPurchaseForCategory().
     */
    private function findValidPurchaseForCategory(int $customerId, ?string $sessionCategory, int $persons): ?CustomerPackagePurchaseModel
    {
        if ($sessionCategory === null) {
            return null;
        }
        $bizTz = (string) config('app.business_timezone');
        $today = Carbon::now($bizTz)->startOfDay();
        $purchases = CustomerPackagePurchaseModel::query()
            ->with(['package.services'])
            ->where('customer_id', $customerId)
            ->where('status', 'active')
            ->where('remaining_sessions', '>=', $persons)
            ->whereNotNull('package_id')
            ->orderByDesc('purchase_date')
            ->get();

        foreach ($purchases as $purchase) {
            $package = $purchase->package;
            if (! $package) {
                continue;
            }
            $packageCategory = $this->packageCategory($package);
            if ($packageCategory !== $sessionCategory) {
                continue;
            }
            $expiresAt = PackagePurchaseExpiry::expiresAt(
                $package,
                $purchase->purchase_date,
                $purchase->amelia_package_id,
                (bool) $purchase->expires_by_months_only,
            );
            if ($expiresAt !== null && $expiresAt->copy()->timezone($bizTz)->startOfDay()->lt($today)) {
                continue;
            }

            return $purchase;
        }

        return null;
    }

    /**
     * Determine package category (Yoga / Reformer Pilates) from package.service_type (admin-set) first,
     * then fallback to service names if service_type is not set.
     * MUST stay in sync with MobileBookingController::packageCategory().
     */
    private function packageCategory($package): ?string
    {
        if (! $package) {
            return null;
        }
        $serviceType = $package->service_type ?? null;
        if ($serviceType !== null) {
            $lower = strtolower((string) $serviceType);
            if (str_contains($lower, 'reformer') || str_contains($lower, 'reform')) {
                return 'Reformer Pilates';
            }
            if (str_contains($lower, 'yoga')) {
                return 'Yoga';
            }
        }
        if ($package->relationLoaded('services') && $package->services->isNotEmpty()) {
            $yogaCount = 0;
            $reformerCount = 0;
            foreach ($package->services as $service) {
                $name = strtolower($service->name ?? '');
                if (str_contains($name, 'reformer') || str_contains($name, 'reform pilates')) {
                    $reformerCount++;
                } elseif (str_contains($name, 'yoga')) {
                    $yogaCount++;
                }
            }
            if ($reformerCount > 0 && $reformerCount >= $yogaCount) {
                return 'Reformer Pilates';
            }
            if ($yogaCount > 0) {
                return 'Yoga';
            }
        }

        return null;
    }
}
