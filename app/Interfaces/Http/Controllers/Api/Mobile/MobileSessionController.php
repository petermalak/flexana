<?php

namespace App\Interfaces\Http\Controllers\Api\Mobile;

use App\Console\Commands\CategorizeServicesCommand;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\AppointmentModel;
use App\Models\Category;
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
        $perPage = max(1, min(50, (int) $request->query('per_page', 15)));

        $query = AppointmentModel::query()
            ->with(['service', 'provider', 'bookings'])
            ->where('status', 'approved');

        if ($date) {
            try {
                $dateCarbon = Carbon::parse($date);
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

        $appointments = $query->paginate($perPage);
        $customerId = $request->user()?->id;

        $items = $appointments->getCollection()->map(function ($appointment) use ($customerId) {
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
                ? Carbon::parse($appointment->booking_start)->subMinutes($minutesBeforeCancellation)
                : null;
            $canCancel = $isBooked && $canCancelUntil && Carbon::now()->lte($canCancelUntil);
            $canBook = ! $isFull && Carbon::parse($appointment->booking_start)->gt(Carbon::now());

            $serviceText = $service ? (($service->name ?? '') . ' ' . ($service->description ?? '')) : '';
            $serviceType = CategorizeServicesCommand::inferCategoryNameFromText($serviceText);

            return [
                'id' => (string) $appointment->id,
                'bookingId' => $myBooking ? (string) $myBooking->id : null,
                'instructor' => $provider ? $provider->name : '',
                'service' => $service ? $service->name : '',
                'serviceType' => $serviceType,
                'date' => Carbon::parse($appointment->booking_start)->toIso8601String(),
                'isBooked' => $isBooked,
                'isFull' => $isFull,
                'canCancel' => $canCancel,
                'canBook' => $canBook,
                'minutesBeforeCancellation' => $minutesBeforeCancellation,
            ];
        });

        return response()->json([
            'data' => $items->values()->toArray(),
            'meta' => [
                'current_page' => $appointments->currentPage(),
                'last_page' => $appointments->lastPage(),
                'per_page' => $appointments->perPage(),
                'total' => $appointments->total(),
            ],
        ]);
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
        [$categoryIds, $regexpPattern] = $this->getCategoryFilterData($canonicalType);

        $query->whereHas('service', function ($q) use ($categoryIds, $regexpPattern) {
            $q->where(function ($q2) use ($categoryIds, $regexpPattern) {
                if (count($categoryIds) > 0) {
                    $q2->whereIn('category_id', $categoryIds);
                }
                if ($regexpPattern !== '') {
                    $q2->orWhereRaw(
                        'LOWER(CONCAT(COALESCE(name,""), " ", COALESCE(description,""))) REGEXP ?',
                        [$regexpPattern]
                    );
                }
                if (count($categoryIds) === 0 && $regexpPattern === '') {
                    $q2->whereRaw('1 = 0');
                }
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
}
