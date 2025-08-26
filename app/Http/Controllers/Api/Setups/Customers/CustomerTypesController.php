<?php

namespace App\Http\Controllers\Api\Setups\Customers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Setups\Customers\CustomerTypesStoreRequest;
use App\Http\Requests\Api\Setups\Customers\CustomerTypesUpdateRequest;
use App\Http\Resources\Api\Setups\Customers\CustomerTypeResource;
use App\Http\Responses\ApiResponse;
use App\Models\Setups\Customers\CustomerType;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerTypesController extends Controller
{
    use HasPagination;

    public function index(Request $request): JsonResponse
    {
        $query = CustomerType::query()
            ->with(['createdBy:id,name', 'updatedBy:id,name'])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $customerTypes = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Customer types retrieved successfully',
            $customerTypes,
            CustomerTypeResource::class
        );
    }

    public function store(CustomerTypesStoreRequest $request): JsonResponse
    {
        $customerType = CustomerType::create($request->validated());
        $customerType->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::store(
            'Customer type created successfully',
            new CustomerTypeResource($customerType)
        );
    }

    public function show(CustomerType $customerType): JsonResponse
    {
        $customerType->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::show(
            'Customer type retrieved successfully',
            new CustomerTypeResource($customerType)
        );
    }

    public function update(CustomerTypesUpdateRequest $request, CustomerType $customerType): JsonResponse
    {
        $customerType->update($request->validated());
        $customerType->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update(
            'Customer type updated successfully',
            new CustomerTypeResource($customerType)
        );
    }

    public function destroy(CustomerType $customerType): JsonResponse
    {
        $customerType->delete();

        return ApiResponse::delete('Customer type deleted successfully');
    }

    public function trashed(Request $request): JsonResponse
    {
        $query = CustomerType::onlyTrashed()
            ->with(['createdBy:id,name', 'updatedBy:id,name'])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $customerTypes = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Trashed customer types retrieved successfully',
            $customerTypes,
            CustomerTypeResource::class
        );
    }

    public function restore(int $id): JsonResponse
    {
        $customerType = CustomerType::onlyTrashed()->findOrFail($id);
        $customerType->restore();
        $customerType->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update(
            'Customer type restored successfully',
            new CustomerTypeResource($customerType)
        );
    }

    public function forceDelete(int $id): JsonResponse
    {
        $customerType = CustomerType::onlyTrashed()->findOrFail($id);
        $customerType->forceDelete();

        return ApiResponse::delete('Customer type permanently deleted successfully');
    }
}
