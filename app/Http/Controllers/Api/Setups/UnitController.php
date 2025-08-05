<?php

namespace App\Http\Controllers\Api\Setups;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Setups\UnitStoreRequest;
use App\Http\Requests\Api\Setups\UnitUpdateRequest;
use App\Http\Resources\Api\Setups\UnitResource;
use App\Http\Responses\ApiResponse;
use App\Models\Unit;
use App\Traits\HasBooleanFilters;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UnitController extends Controller
{
    use HasPagination, HasBooleanFilters;

    public function index(Request $request): JsonResponse
    {
        $query = Unit::with(['createdBy:id,name', 'updatedBy:id,name'])
            ->searchable($request)
            ->sortable($request);

        $this->applyBooleanFilter($query, 'is_active', $request->input('is_active'));

        $units = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Units retrieved successfully',
            $units,
            UnitResource::class
        );
    }

    public function store(UnitStoreRequest $request): JsonResponse
    {
        $unit = Unit::create($request->validated());
        $unit->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::store(
            'Unit created successfully',
            new UnitResource($unit)
        );
    }

    public function show(Unit $unit): JsonResponse
    {
        $unit->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::show(
            'Unit retrieved successfully',
            new UnitResource($unit)
        );
    }

    public function update(UnitUpdateRequest $request, Unit $unit): JsonResponse
    {
        $unit->update($request->validated());
        $unit->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update(
            'Unit updated successfully',
            new UnitResource($unit)
        );
    }

    public function destroy(Unit $unit): JsonResponse
    {
        $unit->delete();

        return ApiResponse::delete('Unit deleted successfully');
    }

    public function active(Request $request): JsonResponse
    {
        $units = Unit::active()
            ->select('id', 'name', 'short_name')
            ->orderBy('name')
            ->get();

        return ApiResponse::index(
            'Active units retrieved successfully',
            $units
        );
    }
}