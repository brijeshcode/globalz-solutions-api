<?php

namespace App\Http\Controllers\Api\Setups\Customers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Setups\Customers\CustomerZonesStoreRequest;
use App\Http\Requests\Api\Setups\Customers\CustomerZonesUpdateRequest;
use App\Http\Resources\Api\Setups\Customers\CustomerZoneResource;
use App\Http\Responses\ApiResponse;
use App\Models\Setups\Customers\CustomerZone;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerZonesController extends Controller
{
    use HasPagination;

    public function index(Request $request): JsonResponse
    {
        $query = CustomerZone::query()
            ->with(['createdBy:id,name', 'updatedBy:id,name'])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $customerZones = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Customer zones retrieved successfully',
            $customerZones,
            CustomerZoneResource::class
        );
    }

    public function store(CustomerZonesStoreRequest $request): JsonResponse
    {
        $customerZone = CustomerZone::create($request->validated());
        $customerZone->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::store(
            'Customer zone created successfully',
            new CustomerZoneResource($customerZone)
        );
    }

    public function show(CustomerZone $customerZone): JsonResponse
    {
        $customerZone->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::show(
            'Customer zone retrieved successfully',
            new CustomerZoneResource($customerZone)
        );
    }

    public function update(CustomerZonesUpdateRequest $request, CustomerZone $customerZone): JsonResponse
    {
        $customerZone->update($request->validated());
        $customerZone->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update(
            'Customer zone updated successfully',
            new CustomerZoneResource($customerZone)
        );
    }

    public function destroy(CustomerZone $customerZone): JsonResponse
    {
        $customerZone->delete();

        return ApiResponse::delete('Customer zone deleted successfully');
    }

    public function trashed(Request $request): JsonResponse
    {
        $query = CustomerZone::onlyTrashed()
            ->with(['createdBy:id,name', 'updatedBy:id,name'])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $customerZones = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Trashed customer zones retrieved successfully',
            $customerZones,
            CustomerZoneResource::class
        );
    }

    public function restore(int $id): JsonResponse
    {
        $customerZone = CustomerZone::onlyTrashed()->findOrFail($id);
        $customerZone->restore();
        $customerZone->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update(
            'Customer zone restored successfully',
            new CustomerZoneResource($customerZone)
        );
    }

    public function forceDelete(int $id): JsonResponse
    {
        $customerZone = CustomerZone::onlyTrashed()->findOrFail($id);
        $customerZone->forceDelete();

        return ApiResponse::delete('Customer zone permanently deleted successfully');
    }
}
