<?php

namespace App\Http\Controllers\Api\Setups\Customers;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Setups\Customers\CustomerGroupsStoreRequest;
use App\Http\Requests\Api\Setups\Customers\CustomerGroupsUpdateRequest;
use App\Http\Resources\Api\Setups\Customers\CustomerGroupResource;
use App\Http\Responses\ApiResponse;
use App\Models\Setups\Customers\CustomerGroup;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerGroupsController extends Controller
{
    use HasPagination;

    public function index(Request $request): JsonResponse
    {
        $query = CustomerGroup::query()
            ->with(['createdBy:id,name', 'updatedBy:id,name'])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $groups = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Groups retrieved successfully',
            $groups,
            CustomerGroupResource::class
        );
    }

    public function store(CustomerGroupsStoreRequest $request): JsonResponse
    {
        $group = CustomerGroup::create($request->validated());
        $group->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::store(
            'Group created successfully',
            new CustomerGroupResource($group)
        );
    }

    public function show(CustomerGroup $customerGroup): JsonResponse
    {
        $customerGroup->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::show(
            'Group retrieved successfully',
            new CustomerGroupResource($customerGroup)
        );
    }

    public function update(CustomerGroupsUpdateRequest $request, CustomerGroup $customerGroup): JsonResponse
    {
        $customerGroup->update($request->validated());
        $customerGroup->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update(
            'Group updated successfully',
            new CustomerGroupResource($customerGroup)
        );
    }

    public function destroy(CustomerGroup $customerGroup): JsonResponse
    {
        $customerGroup->delete();

        return ApiResponse::delete('Group deleted successfully');
    }

    public function trashed(Request $request): JsonResponse
    {
        $query = CustomerGroup::onlyTrashed()
            ->with(['createdBy:id,name', 'updatedBy:id,name'])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $groups = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Trashed groups retrieved successfully',
            $groups,
            CustomerGroupResource::class
        );
    }

    public function restore(int $id): JsonResponse
    {
        $group = CustomerGroup::onlyTrashed()->findOrFail($id);
        $group->restore();
        $group->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update(
            'Group restored successfully',
            new CustomerGroupResource($group)
        );
    }

    public function forceDelete(int $id): JsonResponse
    {
        $group = CustomerGroup::onlyTrashed()->findOrFail($id);
        $group->forceDelete();

        return ApiResponse::delete('Group permanently deleted successfully');
    }
}
