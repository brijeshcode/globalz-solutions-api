<?php

namespace App\Http\Controllers\Api\Setups;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Setups\ItemGroupsStoreRequest;
use App\Http\Requests\Api\Setups\ItemGroupsUpdateRequest;
use App\Http\Resources\Api\Setups\ItemGroupResource;
use App\Http\Responses\ApiResponse;
use App\Models\Setups\ItemGroup;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItemGroupsController extends Controller
{
    use HasPagination;

    public function index(Request $request): JsonResponse
    {
        $query = ItemGroup::query()
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
            ItemGroupResource::class
        );
    }

    public function store(ItemGroupsStoreRequest $request): JsonResponse
    {
        $group = ItemGroup::create($request->validated());
        $group->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::store(
            'Group created successfully',
            new ItemGroupResource($group)
        );
    }

    public function show(ItemGroup $group): JsonResponse
    {
        $group->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::show(
            'Group retrieved successfully',
            new ItemGroupResource($group)
        );
    }

    public function update(ItemGroupsUpdateRequest $request, ItemGroup $group): JsonResponse
    {
        $group->update($request->validated());
        $group->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update(
            'Group updated successfully',
            new ItemGroupResource($group)
        );
    }

    public function destroy(ItemGroup $group): JsonResponse
    {
        $group->delete();

        return ApiResponse::delete('Group deleted successfully');
    }

    public function trashed(Request $request): JsonResponse
    {
        $query = ItemGroup::onlyTrashed()
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
            ItemGroupResource::class
        );
    }

    public function restore(int $id): JsonResponse
    {
        $group = ItemGroup::onlyTrashed()->findOrFail($id);
        $group->restore();
        $group->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update(
            'Group restored successfully',
            new ItemGroupResource($group)
        );
    }

    public function forceDelete(int $id): JsonResponse
    {
        $group = ItemGroup::onlyTrashed()->findOrFail($id);
        $group->forceDelete();

        return ApiResponse::delete('Group permanently deleted successfully');
    }
}