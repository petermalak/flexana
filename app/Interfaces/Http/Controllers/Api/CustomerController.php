<?php

namespace App\Interfaces\Http\Controllers\Api;

use App\Application\Customers\Services\CustomerService;
use App\Http\Controllers\Controller;
use App\Interfaces\Http\Requests\Api\CustomerUpsertRequest;
use App\Interfaces\Http\Resources\CustomerResource;
use App\Interfaces\Http\Resources\FlutterCustomerResource;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    public function __construct(
        private readonly CustomerService $customers,
    ) {
    }

    public function index(Request $request)
    {
        $customers = $this->customers->paginate(
            filters: $request->only(['search', 'source']),
            perPage: (int) $request->integer('per_page', 25)
        );

        return CustomerResource::collection($customers);
    }

    public function store(CustomerUpsertRequest $request)
    {
        $customer = $this->customers->create($request->payload());

        return new CustomerResource($customer);
    }

    public function update(string $customer, CustomerUpsertRequest $request)
    {
        $updated = $this->customers->update($customer, $request->payload());

        return new CustomerResource($updated);
    }

    public function storeFlutter(\App\Interfaces\Http\Requests\Api\FlutterCustomerStoreRequest $request)
    {
        $data = $request->validated();
        
        // Map Flutter format to internal format
        $payload = \App\Application\Customers\Data\CustomerUpsertData::from([
            'firstName' => $data['firstName'],
            'lastName' => $data['lastName'] ?? null,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
            'timezone' => 'UTC', // Default timezone
            'preferences' => [
                'externalId' => $data['externalId'] ?? null,
                'countryPhoneIso' => $data['countryPhoneIso'] ?? null,
                'gender' => $data['gender'] ?? null,
                'birthday' => $data['birthday'] ?? null,
                'language' => $data['language'] ?? null,
            ],
            'source' => 'flutter_app',
            'notes' => $data['note'] ?? null,
        ]);

        $customer = $this->customers->create($payload);
        
        // Get the model with ID for Flutter response
        $customerModel = \App\Infrastructure\Persistence\Eloquent\CustomerModel::where('uuid', $customer->uuid)->first();
        
        return new FlutterCustomerResource($customerModel ?? $customer);
    }
}

