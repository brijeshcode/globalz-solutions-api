<?php

namespace App\Http\Controllers\Api\Setups;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Setups\WarehousesStoreRequest;
use App\Http\Requests\Api\Setups\WarehousesUpdateRequest;
use App\Http\Resources\Api\Setups\WarehouseResource;
use App\Http\Responses\ApiResponse;
use App\Models\Setups\Warehouse;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WarehousesController extends Controller
{
    use HasPagination;

    public function index(Request $request): JsonResponse
    {
        $query = Warehouse::query()
            ->with(['createdBy:id,name', 'updatedBy:id,name'])
            ->searchable($request)
            ->sortable($request)
            ;

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        if ($request->has('city')) {
            $query->where('city', 'like', '%' . $request->city . '%');
        }

        if ($request->has('state')) {
            $query->where('state', 'like', '%' . $request->state . '%');
        }

        if ($request->has('country')) {
            $query->where('country', 'like', '%' . $request->country . '%');
        }

        $warehouses = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Warehouses retrieved successfully',
            $warehouses,
            WarehouseResource::class
        );
    }

    public function store(WarehousesStoreRequest $request): JsonResponse
    {
        $warehouse = Warehouse::create($request->validated());
        $warehouse->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::store(
            'Warehouse created successfully',
            new WarehouseResource($warehouse)
        );
    }

    public function show(Warehouse $warehouse): JsonResponse
    {
        $warehouse->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::show(
            'Warehouse retrieved successfully',
            new WarehouseResource($warehouse)
        );
    }

    public function update(WarehousesUpdateRequest $request, Warehouse $warehouse): JsonResponse
    {
        $warehouse->update($request->validated());
        $warehouse->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update(
            'Warehouse updated successfully',
            new WarehouseResource($warehouse)
        );
    }

    public function setDefault(int $warehouseId): JsonResponse
    {
        $warehouse = Warehouse::findorfail($warehouseId);
        Warehouse::where('is_default', true)->update(['is_default' => false]);
        $warehouse->update(['is_default' => true]);
        
        return ApiResponse::update(
            'Warehouse default set',
            new WarehouseResource($warehouse)
        );
    }

    public function destroy(Warehouse $warehouse): JsonResponse
    {
        $warehouse->delete();

        return ApiResponse::delete('Warehouse deleted successfully');
    }

    public function trashed(Request $request): JsonResponse
    {
        $query = Warehouse::onlyTrashed()
            ->with(['createdBy:id,name', 'updatedBy:id,name'])
            ->searchable($request)
            ->sortable($request)
            ;

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }
        if ($request->has('city')) {
            $query->where('city', 'like', '%' . $request->city . '%');
        }

        if ($request->has('state')) {
            $query->where('state', 'like', '%' . $request->state . '%');
        }

        if ($request->has('country')) {
            $query->where('country', 'like', '%' . $request->country . '%');
        }

        $warehouses = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Trashed warehouses retrieved successfully',
            $warehouses,
            WarehouseResource::class
        );
    }

    public function restore(int $id): JsonResponse
    {
        $warehouse = Warehouse::onlyTrashed()->findOrFail($id);
        $warehouse->restore();
        $warehouse->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update(
            'Warehouse restored successfully',
            new WarehouseResource($warehouse)
        );
    }

    public function forceDelete(int $id): JsonResponse
    {
        $warehouse = Warehouse::onlyTrashed()->findOrFail($id);
        $warehouse->forceDelete();

        return ApiResponse::delete('Warehouse permanently deleted successfully');
    }
}