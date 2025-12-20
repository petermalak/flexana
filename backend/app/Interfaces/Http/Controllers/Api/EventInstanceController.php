<?php

namespace App\Interfaces\Http\Controllers\Api;

use App\Application\Events\Services\EventInstanceService;
use App\Application\Events\Data\EventInstanceUpsertData;
use App\Http\Controllers\Controller;
use App\Interfaces\Http\Resources\EventInstanceResource;
use Illuminate\Http\Request;

class EventInstanceController extends Controller
{
    public function __construct(
        private readonly EventInstanceService $eventInstances,
    ) {
    }

    public function index(Request $request)
    {
        $instances = $this->eventInstances->paginate(
            filters: $request->only(['search', 'status', 'event_id']),
            perPage: (int) $request->integer('per_page', 25)
        );

        return EventInstanceResource::collection($instances);
    }

    public function store(Request $request)
    {
        $instance = $this->eventInstances->create(EventInstanceUpsertData::from($request->all()));

        return new EventInstanceResource($instance);
    }

    public function show(string $instance)
    {
        $instance = $this->eventInstances->show($instance);

        return new EventInstanceResource($instance);
    }

    public function update(string $instance, Request $request)
    {
        $updated = $this->eventInstances->update($instance, EventInstanceUpsertData::from($request->all()));

        return new EventInstanceResource($updated);
    }

    public function destroy(string $instance)
    {
        $this->eventInstances->delete($instance);

        return response()->noContent();
    }
}

