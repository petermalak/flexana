<?php

namespace App\Interfaces\Http\Controllers\Api;

use App\Application\Events\Services\EventService;
use App\Http\Controllers\Controller;
use App\Interfaces\Http\Requests\Api\EventUpsertRequest;
use App\Interfaces\Http\Resources\EventResource;
use Illuminate\Http\Request;

class EventController extends Controller
{
    public function __construct(
        private readonly EventService $events,
    ) {
    }

    public function index(Request $request)
    {
        $events = $this->events->paginate(
            filters: $request->only(['search', 'status', 'category']),
            perPage: (int) $request->integer('per_page', 25)
        );

        return EventResource::collection($events);
    }

    public function store(EventUpsertRequest $request)
    {
        $event = $this->events->create($request->payload());

        return new EventResource($event);
    }

    public function show(string $event)
    {
        // Note: show method needs to be added to EventService
        // For now, using paginate with filter
        $events = $this->events->paginate(['search' => $event], 1);
        if ($events->isEmpty()) {
            abort(404, 'Event not found.');
        }
        return new EventResource($events->first());
    }

    public function update(string $event, EventUpsertRequest $request)
    {
        $updated = $this->events->update($event, $request->payload());

        return new EventResource($updated);
    }

    public function destroy(string $event)
    {
        $this->events->delete($event);

        return response()->noContent();
    }
}

