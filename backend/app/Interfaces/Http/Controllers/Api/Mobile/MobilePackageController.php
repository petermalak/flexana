<?php

namespace App\Interfaces\Http\Controllers\Api\Mobile;

use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\AmeliaPackageModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class MobilePackageController extends Controller
{
    /**
     * Get packages
     * 
     * @return JsonResponse
     */
    public function index(): JsonResponse
    {
        $packages = AmeliaPackageModel::on('wordpress')
            ->where('status', 'visible')
            ->orderBy('position', 'asc')
            ->get()
            ->map(function ($package) {
                $settings = is_string($package->settings) 
                    ? json_decode($package->settings, true) 
                    : ($package->settings ?? []);
                
                // Calculate expiration months from duration
                $expirationMonths = 0;
                if ($package->durationType === 'months') {
                    $expirationMonths = $package->durationCount ?? 0;
                } elseif ($package->durationType === 'days') {
                    $expirationMonths = (int) ceil(($package->durationCount ?? 0) / 30);
                }

                // Count sessions from package services
                $sessions = DB::connection('wordpress')
                    ->table('rueyn_amelia_packages_services')
                    ->where('packageId', $package->id)
                    ->sum('quantity') ?? 0;

                return [
                    'id' => (int) $package->id,
                    'name' => $package->name ?? '',
                    'price' => (float) ($package->price ?? 0),
                    'sessions' => (int) $sessions,
                    'description' => $package->description ?? '',
                    'expirationMonths' => $expirationMonths,
                ];
            })
            ->values()
            ->toArray();

        return response()->json($packages);
    }

    /**
     * Purchase a package
     * 
     * Body: { packageId, customerId (optional), customer (optional) }
     * 
     * @param Request $request
     * @return JsonResponse
     */
    public function purchase(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'packageId' => 'required|integer',
            'customerId' => 'nullable|integer',
            'customer' => 'nullable|array',
            'customer.firstName' => 'required_with:customer|string',
            'customer.lastName' => 'nullable|string',
            'customer.email' => 'nullable|email',
            'customer.phone' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors(),
            ], 422);
        }

        $data = $validator->validated();
        $packageId = $data['packageId'];

        // Get package
        $package = AmeliaPackageModel::on('wordpress')->find($packageId);

        if (!$package || $package->status !== 'visible') {
            return response()->json([
                'success' => false,
                'message' => 'Package not found or not available',
            ], 404);
        }

        // Validate customerId if provided
        if (!empty($data['customerId'])) {
            $customerExists = \App\Infrastructure\Persistence\Eloquent\AmeliaUserModel::on('wordpress')
                ->where('type', 'customer')
                ->where('id', $data['customerId'])
                ->exists();
            
            if (!$customerExists) {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer not found',
                ], 404);
            }
        }

        // Get or create customer
        $customer = null;
        if (!empty($data['customerId'])) {
            $customer = \App\Infrastructure\Persistence\Eloquent\AmeliaUserModel::on('wordpress')
                ->where('type', 'customer')
                ->find($data['customerId']);
            
            if (!$customer) {
                return response()->json([
                    'success' => false,
                    'message' => 'Customer not found',
                ], 404);
            }
        } elseif (!empty($data['customer'])) {
            // Create new customer
            $customerData = $data['customer'];
            $customer = \App\Infrastructure\Persistence\Eloquent\AmeliaUserModel::on('wordpress')->create([
                'firstName' => $customerData['firstName'],
                'lastName' => $customerData['lastName'] ?? null,
                'email' => $customerData['email'] ?? null,
                'phone' => $customerData['phone'] ?? null,
                'type' => 'customer',
                'status' => 'visible',
                'created' => Carbon::now(),
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => 'Customer ID or customer data is required',
            ], 422);
        }

        // Create package customer record
        DB::connection('wordpress')->beginTransaction();
        try {
            $packageCustomer = DB::connection('wordpress')
                ->table('rueyn_amelia_packages_customers')
                ->insertGetId([
                    'packageId' => $package->id,
                    'customerId' => $customer->id,
                    'price' => $package->price,
                    'purchased' => Carbon::now()->format('Y-m-d H:i:s'),
                    'status' => 'approved',
                ]);

            DB::connection('wordpress')->commit();

            return response()->json([
                'success' => true,
                'message' => 'Package purchased successfully',
                'data' => [
                    'id' => (string) $packageCustomer,
                    'packageId' => (string) $package->id,
                    'customerId' => (string) $customer->id,
                ],
            ], 201);
        } catch (\Exception $e) {
            DB::connection('wordpress')->rollBack();
            
            return response()->json([
                'success' => false,
                'message' => 'Failed to purchase package',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
