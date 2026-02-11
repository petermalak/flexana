<?php

namespace App\Interfaces\Http\Controllers\Api;

use App\Application\ClassTypes\Services\ClassTypeService;
use App\Application\ClassTypes\Data\ClassTypeUpsertData;
use App\Http\Controllers\Controller;
use App\Interfaces\Http\Resources\ClassTypeResource;
use Illuminate\Http\Request;

class ClassTypeController extends Controller
{
    public function __construct(
        private readonly ClassTypeService $classTypes,
    ) {
    }

    public function index(Request $request)
    {
        $classTypes = $this->classTypes->paginate(
            filters: $request->only(['search', 'is_active']),
            perPage: (int) $request->integer('per_page', 25)
        );

        return ClassTypeResource::collection($classTypes);
    }

    public function store(Request $request)
    {
        $classType = $this->classTypes->create(ClassTypeUpsertData::from($request->all()));

        return new ClassTypeResource($classType);
    }

    public function show(string $classType)
    {
        $classType = $this->classTypes->show($classType);

        return new ClassTypeResource($classType);
    }

    public function update(string $classType, Request $request)
    {
        $updated = $this->classTypes->update($classType, ClassTypeUpsertData::from($request->all()));

        return new ClassTypeResource($updated);
    }

    public function destroy(string $classType)
    {
        $this->classTypes->delete($classType);

        return response()->noContent();
    }
}

