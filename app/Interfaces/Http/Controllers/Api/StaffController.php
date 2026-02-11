<?php

namespace App\Interfaces\Http\Controllers\Api;

use App\Application\Staff\Services\StaffService;
use App\Application\Staff\Data\StaffUpsertData;
use App\Http\Controllers\Controller;
use App\Interfaces\Http\Resources\StaffResource;
use Illuminate\Http\Request;

class StaffController extends Controller
{
    public function __construct(
        private readonly StaffService $staff,
    ) {
    }

    public function index(Request $request)
    {
        $staff = $this->staff->paginate(
            filters: $request->only(['search', 'role', 'is_active']),
            perPage: (int) $request->integer('per_page', 25)
        );

        return StaffResource::collection($staff);
    }

    public function store(Request $request)
    {
        $staff = $this->staff->create(StaffUpsertData::from($request->all()));

        return new StaffResource($staff);
    }

    public function show(string $staff)
    {
        $staff = $this->staff->show($staff);

        return new StaffResource($staff);
    }

    public function update(string $staff, Request $request)
    {
        $updated = $this->staff->update($staff, StaffUpsertData::from($request->all()));

        return new StaffResource($updated);
    }

    public function destroy(string $staff)
    {
        $this->staff->delete($staff);

        return response()->noContent();
    }
}

