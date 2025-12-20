<?php

namespace App\Interfaces\Http\Controllers\Api;

use App\Application\Events\Services\EventService;
use App\Application\Services\Services\ServiceService;
use App\Application\Services\Data\ServiceUpsertData;
use App\Http\Controllers\Controller;
use App\Interfaces\Http\Requests\Api\FlutterServiceStoreRequest;
use App\Interfaces\Http\Resources\EventResource;
use App\Interfaces\Http\Resources\FlutterServiceResource;
use App\Interfaces\Http\Resources\ServiceResource;
use Illuminate\Http\Request;

class ServiceController extends Controller
{
    public function __construct(
        private readonly ServiceService $services,
        private readonly EventService $events,
    ) {
    }

    public function store(FlutterServiceStoreRequest $request)
    {
        $data = $request->validated();
        
        // Map Flutter service status to Event status
        $statusMap = [
            'visible' => 'published',
            'hidden' => 'draft',
        ];
        $status = $statusMap[$data['status'] ?? 'visible'] ?? 'published';
        
        // Map Flutter service format to Event format
        $eventPayload = \App\Application\Events\Data\EventUpsertData::from([
            'name' => $data['name'],
            'slug' => null, // Will be auto-generated
            'category' => $data['categoryId'] ?? null,
            'description' => $data['description'] ?? null,
            'status' => $status,
            'timezone' => 'UTC',
            'capacity' => $data['maxCapacity'] ?? $data['minCapacity'] ?? null,
            'price' => (float) ($data['price'] ?? 0),
            'depositAmount' => (float) ($data['deposit'] ?? 0),
            'allowWaitlist' => $data['bringingAnyone'] ?? false,
            'recurrence' => null,
            'meta' => [
                'duration' => $data['duration'] ?? null,
                'providers' => $data['providers'] ?? [],
                'extras' => $data['extras'] ?? [],
                'minCapacity' => $data['minCapacity'] ?? 1,
                'maxCapacity' => $data['maxCapacity'] ?? 1,
                'pictureFullPath' => $data['pictureFullPath'] ?? null,
                'pictureThumbPath' => $data['pictureThumbPath'] ?? null,
                'color' => $data['color'] ?? null,
                'settings' => $data['settings'] ? json_decode($data['settings'], true) : null,
                'gallery' => $data['gallery'] ?? [],
                'position' => $data['position'] ?? 0,
                'customPricing' => $data['customPricing'] ? json_decode($data['customPricing'], true) : null,
                'limitPerCustomer' => $data['limitPerCustomer'] ? json_decode($data['limitPerCustomer'], true) : null,
            ],
        ]);

        $event = $this->events->create($eventPayload);
        
        // Get the model with ID for Flutter response
        $eventModel = \App\Infrastructure\Persistence\Eloquent\EventModel::where('uuid', $event->uuid)->first();
        
        return new FlutterServiceResource($eventModel ?? $event);
    }

    public function index(Request $request)
    {
        $services = $this->services->paginate(
            filters: $request->only(['search', 'status']),
            perPage: (int) $request->integer('per_page', 25)
        );

        return ServiceResource::collection($services);
    }

    public function show(string $service)
    {
        $service = $this->services->show($service);

        return new ServiceResource($service);
    }

    public function update(string $service, Request $request)
    {
        $updated = $this->services->update($service, ServiceUpsertData::from($request->all()));

        return new ServiceResource($updated);
    }

    public function destroy(string $service)
    {
        $this->services->delete($service);

        return response()->noContent();
    }
}

