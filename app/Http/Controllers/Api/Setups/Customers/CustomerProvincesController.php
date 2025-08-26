<?php

namespace App\Http\Controllers\Api\Setups\Customers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Setups\Customers\CustomerProvincesStoreRequest;
use App\Http\Requests\Api\Setups\Customers\CustomerProvincesUpdateRequest;
use App\Http\Resources\Api\Setups\Customers\CustomerProvinceResource;
use App\Http\Responses\ApiResponse;
use App\Models\Setups\Customers\CustomerProvince;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerProvincesController extends Controller
{
    use HasPagination;

    public function index(Request $request): JsonResponse
    {
        $query = CustomerProvince::query()
            ->with(['createdBy:id,name', 'updatedBy:id,name'])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $customerProvinces = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Customer provinces retrieved successfully',
            $customerProvinces,
            CustomerProvinceResource::class
        );
    }

    public function store(CustomerProvincesStoreRequest $request): JsonResponse
    {
        $customerProvince = CustomerProvince::create($request->validated());
        $customerProvince->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::store(
            'Customer province created successfully',
            new CustomerProvinceResource($customerProvince)
        );
    }

    public function show(CustomerProvince $customerProvince): JsonResponse
    {
        $customerProvince->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::show(
            'Customer province retrieved successfully',
            new CustomerProvinceResource($customerProvince)
        );
    }

    public function update(CustomerProvincesUpdateRequest $request, CustomerProvince $customerProvince): JsonResponse
    {
        $customerProvince->update($request->validated());
        $customerProvince->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update(
            'Customer province updated successfully',
            new CustomerProvinceResource($customerProvince)
        );
    }

    public function destroy(CustomerProvince $customerProvince): JsonResponse
    {
        $customerProvince->delete();

        return ApiResponse::delete('Customer province deleted successfully');
    }

    public function trashed(Request $request): JsonResponse
    {
        $query = CustomerProvince::onlyTrashed()
            ->with(['createdBy:id,name', 'updatedBy:id,name'])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $customerProvinces = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Trashed customer provinces retrieved successfully',
            $customerProvinces,
            CustomerProvinceResource::class
        );
    }

    public function restore(int $id): JsonResponse
    {
        $customerProvince = CustomerProvince::onlyTrashed()->findOrFail($id);
        $customerProvince->restore();
        $customerProvince->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update(
            'Customer province restored successfully',
            new CustomerProvinceResource($customerProvince)
        );
    }

    public function forceDelete(int $id): JsonResponse
    {
        $customerProvince = CustomerProvince::onlyTrashed()->findOrFail($id);
        $customerProvince->forceDelete();

        return ApiResponse::delete('Customer province permanently deleted successfully');
    }
}
