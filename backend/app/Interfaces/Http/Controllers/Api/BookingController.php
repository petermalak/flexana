<?php

namespace App\Interfaces\Http\Controllers\Api;

use App\Application\Bookings\Services\BookingService;
use App\Application\Customers\Services\CustomerService;
use App\Domain\Customers\CustomerRepositoryInterface;
use App\Domain\Events\EventRepositoryInterface;
use App\Http\Controllers\Controller;
use App\Infrastructure\Persistence\Eloquent\EventModel;
use App\Interfaces\Http\Requests\Api\BookingStoreRequest;
use App\Interfaces\Http\Requests\Api\FlutterBookingStoreRequest;
use App\Interfaces\Http\Resources\BookingResource;
use App\Interfaces\Http\Resources\FlutterBookingResource;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class BookingController extends Controller
{
    public function __construct(
        private readonly BookingService $bookings,
        private readonly CustomerService $customers,
        private readonly CustomerRepositoryInterface $customerRepository,
        private readonly EventRepositoryInterface $eventRepository,
    ) {
    }

    public function index(Request $request)
    {
        $bookings = $this->bookings->paginate(
            filters: $request->only(['search', 'status', 'payment_status']),
            perPage: (int) $request->integer('per_page', 25)
        );

        return BookingResource::collection($bookings);
    }

    public function store(Request $request)
    {
        // Detect Flutter format by checking for 'type' and 'bookings' fields
        if ($request->has('type') && $request->has('bookings')) {
            $flutterRequest = \App\Interfaces\Http\Requests\Api\FlutterBookingStoreRequest::createFrom($request);
            $flutterRequest->setContainer(app());
            $flutterRequest->validateResolved();
            
            return $this->storeFlutter($flutterRequest);
        }

        // Use original format
        $bookingRequest = BookingStoreRequest::createFrom($request);
        $bookingRequest->setContainer(app());
        $bookingRequest->validateResolved();
        
        $booking = $this->bookings->create($bookingRequest->payload());

        return new BookingResource($booking);
    }

    public function storeFlutter(FlutterBookingStoreRequest $request)
    {
        $data = $request->validated();
        $type = $data['type'];
        $createdBookings = [];

        foreach ($data['bookings'] as $bookingData) {
            // Handle customer creation or retrieval
            $customer = null;
            if (!empty($bookingData['customerId'])) {
                $customer = $this->customerRepository->findByUuid($bookingData['customerId']);
                abort_if(!$customer, 404, 'Customer not found.');
            } else {
                // Create customer from booking data
                $customerData = $bookingData['customer'] ?? [];
                $customerPayload = \App\Application\Customers\Data\CustomerUpsertData::from([
                    'firstName' => $customerData['firstName'] ?? 'Unknown',
                    'lastName' => $customerData['lastName'] ?? null,
                    'email' => $customerData['email'] ?? null,
                    'phone' => $customerData['phone'] ?? null,
                    'timezone' => $data['timeZone'] ?? 'UTC',
                    'preferences' => [
                        'externalId' => $customerData['externalId'] ?? null,
                        'countryPhoneIso' => $customerData['countryPhoneIso'] ?? null,
                    ],
                    'source' => 'flutter_app',
                    'notes' => null,
                ]);
                $customer = $this->customers->create($customerPayload);
                // Get domain customer from repository
                $customer = $this->customerRepository->findByUuid($customer->uuid);
            }

            if ($type === 'appointment') {
                // Find event by serviceId (treating serviceId as event ID)
                $eventModel = EventModel::find($data['serviceId']);
                abort_if(!$eventModel, 404, 'Service/Event not found.');
                
                $event = $this->eventRepository->findByUuid($eventModel->uuid);
                abort_if(!$event, 404, 'Event not found.');

                // Calculate pricing
                $partySize = $bookingData['persons'] ?? 1;
                $basePrice = (float) $event->price;
                $totalAmount = $basePrice * $partySize;
                $depositAmount = $bookingData['deposit'] ? (float) ($event->depositAmount * $partySize) : 0;
                $balanceAmount = $totalAmount - $depositAmount;

                // Parse booking start date
                $bookingStart = Carbon::parse($data['bookingStart'], $data['timeZone'] ?? 'UTC');

                // Create booking
                $bookingPayload = \App\Application\Bookings\Data\BookingCreateData::from([
                    'eventUuid' => $event->uuid,
                    'eventInstanceUuid' => null,
                    'customerUuid' => $customer->uuid,
                    'partySize' => $partySize,
                    'totalAmount' => $totalAmount,
                    'depositAmount' => $depositAmount,
                    'balanceAmount' => $balanceAmount,
                    'currency' => $data['payment']['currency'] ?? 'USD',
                    'channel' => 'mobile',
                    'answers' => $bookingData['customFields'] ?? null,
                    'notes' => null,
                ]);

                $booking = $this->bookings->create($bookingPayload);
                
                // Update booked_at timestamp and get the model with ID
                $bookingModel = \App\Infrastructure\Persistence\Eloquent\BookingModel::where('uuid', $booking->uuid)->first();
                if ($bookingModel) {
                    $bookingModel->update(['booked_at' => $bookingStart]);
                    $bookingModel->refresh(); // Refresh to ensure all attributes are current
                    $createdBookings[] = $bookingModel;
                } else {
                    $createdBookings[] = $booking;
                }
            } elseif ($type === 'package') {
                // Handle package bookings (multiple appointments)
                // For now, create a booking for each package item
                foreach ($data['package'] ?? [] as $packageItem) {
                    $eventModel = EventModel::find($packageItem['serviceId']);
                    abort_if(!$eventModel, 404, 'Service/Event not found.');
                    
                    $event = $this->eventRepository->findByUuid($eventModel->uuid);
                    abort_if(!$event, 404, 'Event not found.');

                    $partySize = $bookingData['persons'] ?? 1;
                    $basePrice = (float) $event->price;
                    $totalAmount = $basePrice * $partySize;
                    $depositAmount = $bookingData['deposit'] ? (float) ($event->depositAmount * $partySize) : 0;
                    $balanceAmount = $totalAmount - $depositAmount;

                    $bookingStart = Carbon::parse($packageItem['bookingStart'], $data['timeZone'] ?? 'UTC');

                    $bookingPayload = \App\Application\Bookings\Data\BookingCreateData::from([
                        'eventUuid' => $event->uuid,
                        'eventInstanceUuid' => null,
                        'customerUuid' => $customer->uuid,
                        'partySize' => $partySize,
                        'totalAmount' => $totalAmount,
                        'depositAmount' => $depositAmount,
                        'balanceAmount' => $balanceAmount,
                        'currency' => $data['payment']['currency'] ?? 'USD',
                        'channel' => 'mobile',
                        'answers' => $bookingData['customFields'] ?? null,
                        'notes' => null,
                    ]);

                    $booking = $this->bookings->create($bookingPayload);
                    
                    $bookingModel = \App\Infrastructure\Persistence\Eloquent\BookingModel::where('uuid', $booking->uuid)->first();
                    if ($bookingModel) {
                        $bookingModel->update(['booked_at' => $bookingStart]);
                        $bookingModel->refresh(); // Refresh to ensure all attributes are current
                        $createdBookings[] = $bookingModel;
                    } else {
                        $createdBookings[] = $booking;
                    }
                }
            }
        }

        // Return Flutter-compatible response format
        // WordPress/Emilia typically returns the booking object with id
        if (count($createdBookings) === 1) {
            return new FlutterBookingResource($createdBookings[0]);
        }

        return FlutterBookingResource::collection($createdBookings);
    }
}

