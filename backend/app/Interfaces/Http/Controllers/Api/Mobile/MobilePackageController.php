<?php

namespace App\Interfaces\Http\Controllers\Api\Mobile;

use App\Application\Auth\AmeliaCustomerResolver;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\AmeliaPackageModel;
use App\Infrastructure\Persistence\Eloquent\AmeliaUserModel;
use App\Infrastructure\Persistence\Eloquent\CustomerPackagePurchaseModel;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MobilePackageController extends Controller
{
    public function __construct(
        private readonly AmeliaCustomerResolver $ameliaResolver,
    ) {
    }

    /**
     * Get package offers (Amelia packages).
     */
    public function index(): JsonResponse
    {
        $packages = AmeliaPackageModel::on('wordpress')
            ->where('status', 'visible')
            ->orderBy('position', 'asc')
            ->get()
            ->map(function ($package) {
                $expirationMonths = 0;
                if ($package->durationType === 'months') {
                    $expirationMonths = $package->durationCount ?? 0;
                } elseif ($package->durationType === 'days') {
                    $expirationMonths = (int) ceil(($package->durationCount ?? 0) / 30);
                }

                $sessions = (int) DB::connection('wordpress')
                    ->table('rueyn_amelia_packages_services')
                    ->where('packageId', $package->id)
                    ->sum('quantity');

                return [
                    'id' => (int) $package->id,
                    'name' => $package->name ?? '',
                    'price' => (float) ($package->price ?? 0),
                    'sessions' => $sessions,
                    'description' => $package->description ?? '',
                    'expirationMonths' => $expirationMonths,
                ];
            })
            ->values()
            ->toArray();

        return response()->json($packages);
    }

    /**
     * Purchase a package. Uses authenticated customer (Bearer token).
     * Body: { packageId }
     */
    public function purchase(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'packageId' => 'required|integer',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $packageId = (int) $validator->validated()['packageId'];

        /** @var Customer $laravelCustomer */
        $laravelCustomer = $request->user();

        $package = AmeliaPackageModel::on('wordpress')->find($packageId);

        if (! $package || $package->status !== 'visible') {
            return response()->json([
                'success' => false,
                'message' => 'Package not found or not available',
            ], 404);
        }

        $ameliaUser = $this->ameliaResolver->resolveAmeliaUser($laravelCustomer);
        if (! $ameliaUser) {
            return response()->json([
                'success' => false,
                'message' => 'Could not resolve or create Amelia customer.',
            ], 500);
        }

        $totalSessions = (int) DB::connection('wordpress')
            ->table('rueyn_amelia_packages_services')
            ->where('packageId', $package->id)
            ->sum('quantity');

        DB::connection('wordpress')->beginTransaction();
        try {
            $packageCustomerId = DB::connection('wordpress')
                ->table('rueyn_amelia_packages_customers')
                ->insertGetId([
                    'packageId' => $package->id,
                    'customerId' => $ameliaUser->id,
                    'price' => $package->price,
                    'purchased' => Carbon::now()->format('Y-m-d H:i:s'),
                    'status' => 'approved',
                ]);

            CustomerPackagePurchaseModel::query()->create([
                'customer_id' => $laravelCustomer->id,
                'package_id' => null,
                'amelia_package_id' => $package->id,
                'total_sessions' => $totalSessions,
                'remaining_sessions' => $totalSessions,
                'purchase_date' => Carbon::now(),
                'status' => 'active',
                'amelia_package_customer_id' => $packageCustomerId,
            ]);

            DB::connection('wordpress')->commit();

            return response()->json([
                'success' => true,
                'message' => 'Package purchased successfully',
                'data' => [
                    'id' => (string) $packageCustomerId,
                    'packageId' => (string) $package->id,
                    'customerId' => (string) $laravelCustomer->id,
                ],
            ], 201);
        } catch (\Throwable $e) {
            DB::connection('wordpress')->rollBack();

            return response()->json([
                'success' => false,
                'message' => 'Failed to purchase package',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
