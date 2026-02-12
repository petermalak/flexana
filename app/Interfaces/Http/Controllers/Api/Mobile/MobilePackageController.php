<?php

namespace App\Interfaces\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\BookingModel;
use App\Infrastructure\Persistence\Eloquent\CustomerPackagePurchaseModel;
use App\Infrastructure\Persistence\Eloquent\PackageModel;
use App\Infrastructure\Persistence\Eloquent\PaymentModel;
use App\Infrastructure\Persistence\Eloquent\PromoCodeModel;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MobilePackageController extends Controller
{
    /**
     * Get package offers (Laravel packages) with pagination.
     * Query params: per_page (default 15), page
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = max(1, min(50, (int) $request->query('per_page', 15)));

        $packages = PackageModel::query()
            ->with('services')
            ->where('status', 'active')
            ->orderBy('title')
            ->paginate($perPage);

        $items = $packages->getCollection()->map(function ($package) {
            $sessions = (int) $package->services->sum(fn ($s) => (int) ($s->pivot->quantity ?? 1));
            $expiry = $package->expiry;
            $expirationMonths = 0;
            if ($expiry) {
                $expirationMonths = (int) max(0, Carbon::now()->diffInMonths($expiry, false));
            }

            // Get serviceType from services table - determine category based on service names
            $serviceType = null;
            if ($package->services->isNotEmpty()) {
                $yogaCount = 0;
                $reformerPilatesCount = 0;

                foreach ($package->services as $service) {
                    $serviceName = strtolower($service->name ?? '');
                    if (str_contains($serviceName, 'reformer pilates') || str_contains($serviceName, 'reform pilates')) {
                        $reformerPilatesCount++;
                    } elseif (str_contains($serviceName, 'yoga')) {
                        $yogaCount++;
                    }
                }

                // Determine serviceType based on majority or first match
                if ($reformerPilatesCount > 0 && $reformerPilatesCount >= $yogaCount) {
                    $serviceType = 'Reformer Pilates';
                } elseif ($yogaCount > 0) {
                    $serviceType = 'Yoga';
                }
            }

            // Fallback to classType or service_type field if no services match
            if (!$serviceType) {
                $serviceType = $package->classType?->name ?? $package->service_type ?? null;
            }

            return [
                'id' => (int) $package->id,
                'name' => $package->title ?? '',
                'price' => (float) ($package->price ?? 0),
                'sessions' => $sessions ?: 1,
                'description' => $package->description ?? '',
                'expirationMonths' => $expirationMonths,
                'serviceType' => $serviceType,
                'packageDuration' => $package->package_duration,
            ];
        });

        return response()->json([
            'data' => $items->values()->toArray(),
            'meta' => [
                'current_page' => $packages->currentPage(),
                'last_page' => $packages->lastPage(),
                'per_page' => $packages->perPage(),
                'total' => $packages->total(),
            ],
        ]);
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

        $totalSessions = (int) $package->services->sum(fn ($s) => (int) ($s->pivot->quantity ?? 1)) ?: 1;
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
