<?php

namespace App\Http\Controllers\Api\Setups;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Setups\ItemUnitStoreRequest;
use App\Http\Requests\Api\Setups\ItemUnitUpdateRequest;
use App\Http\Resources\Api\Setups\ItemUnitResource;
use App\Http\Responses\ApiResponse;
use App\Models\Setups\ItemUnit;
use App\Traits\HasBooleanFilters;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItemUnitController extends Controller
{
    use HasPagination, HasBooleanFilters;

    public function index(Request $request): JsonResponse
    {
        $query = ItemUnit::with(['createdBy:id,name', 'updatedBy:id,name'])
            ->searchable($request)
            ->sortable($request);

        $this->applyBooleanFilter($query, 'is_active', $request->input('is_active'));

        $units = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Units retrieved successfully',
            $units,
            ItemUnitResource::class
        );
    }

    public function store(ItemUnitStoreRequest $request): JsonResponse
    {
        $unit = ItemUnit::create($request->validated());
        $unit->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::store(
            'Unit created successfully',
            new ItemUnitResource($unit)
        );
    }

    public function show(ItemUnit $unit): JsonResponse
    {
        $unit->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::show(
            'Unit retrieved successfully',
            new ItemUnitResource($unit)
        );
    }

    public function update(ItemUnitUpdateRequest $request, ItemUnit $unit): JsonResponse
    {
        $unit->update($request->validated());
        $unit->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update(
            'Unit updated successfully',
            new ItemUnitResource($unit)
        );
    }

    public function destroy(ItemUnit $unit): JsonResponse
    {
        $unit->delete();

        return ApiResponse::delete('Unit deleted successfully');
    }

    public function active(Request $request): JsonResponse
    {
        $units = ItemUnit::active()
            ->select('id', 'name', 'short_name')
            ->orderBy('name')
            ->get();

        return ApiResponse::index(
            'Active units retrieved successfully',
            $units
        );
    }
}