<?php

namespace App\Http\Controllers\Api\Setups;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Setups\SupplierTypesStoreRequest;
use App\Http\Requests\Api\Setups\SupplierTypesUpdateRequest;
use App\Http\Resources\Api\Setups\SupplierTypeResource;
use App\Http\Responses\ApiResponse;
use App\Models\Setups\SupplierType;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SupplierTypesController extends Controller
{
    use HasPagination;

    public function index(Request $request): JsonResponse
    {
        $query = SupplierType::query()
            ->with(['createdBy:id,name', 'updatedBy:id,name'])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $supplierTypes = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Supplier types retrieved successfully',
            $supplierTypes,
            SupplierTypeResource::class
        );
    }

    public function store(SupplierTypesStoreRequest $request): JsonResponse
    {
        $supplierType = SupplierType::create($request->validated());
        $supplierType->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::store(
            'Supplier type created successfully',
            new SupplierTypeResource($supplierType)
        );
    }

    public function show(SupplierType $supplierType): JsonResponse
    {
        $supplierType->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::show(
            'Supplier type retrieved successfully',
            new SupplierTypeResource($supplierType)
        );
    }

    public function update(SupplierTypesUpdateRequest $request, SupplierType $supplierType): JsonResponse
    {
        $supplierType->update($request->validated());
        $supplierType->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update(
            'Supplier type updated successfully',
            new SupplierTypeResource($supplierType)
        );
    }

    public function destroy(SupplierType $supplierType): JsonResponse
    {
        $supplierType->delete();

        return ApiResponse::delete('Supplier type deleted successfully');
    }

    public function trashed(Request $request): JsonResponse
    {
        $query = SupplierType::onlyTrashed()
            ->with(['createdBy:id,name', 'updatedBy:id,name'])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $supplierTypes = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Trashed supplier types retrieved successfully',
            $supplierTypes,
            SupplierTypeResource::class
        );
    }

    public function restore(int $id): JsonResponse
    {
        $supplierType = SupplierType::onlyTrashed()->findOrFail($id);
        $supplierType->restore();
        $supplierType->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update(
            'Supplier type restored successfully',
            new SupplierTypeResource($supplierType)
        );
    }

    public function forceDelete(int $id): JsonResponse
    {
        $supplierType = SupplierType::onlyTrashed()->findOrFail($id);
        $supplierType->forceDelete();

        return ApiResponse::delete('Supplier type permanently deleted successfully');
    }
}