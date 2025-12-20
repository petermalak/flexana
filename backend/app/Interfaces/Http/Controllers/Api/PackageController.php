<?php

namespace App\Interfaces\Http\Controllers\Api;

use App\Application\Packages\Services\PackageService;
use App\Application\Packages\Data\PackageUpsertData;
use App\Http\Controllers\Controller;
use App\Interfaces\Http\Resources\PackageResource;
use Illuminate\Http\Request;

class PackageController extends Controller
{
    public function __construct(
        private readonly PackageService $packages,
    ) {
    }

    public function index(Request $request)
    {
        $packages = $this->packages->paginate(
            filters: $request->only(['search', 'status', 'class_type_id']),
            perPage: (int) $request->integer('per_page', 25)
        );

        return PackageResource::collection($packages);
    }

    public function store(Request $request)
    {
        $package = $this->packages->create(PackageUpsertData::from($request->all()));

        return new PackageResource($package);
    }

    public function show(string $package)
    {
        $package = $this->packages->show($package);

        return new PackageResource($package);
    }

    public function update(string $package, Request $request)
    {
        $updated = $this->packages->update($package, PackageUpsertData::from($request->all()));

        return new PackageResource($updated);
    }

    public function destroy(string $package)
    {
        $this->packages->delete($package);

        return response()->noContent();
    }
}

