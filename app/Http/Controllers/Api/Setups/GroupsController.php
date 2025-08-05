<?php

namespace App\Http\Controllers\Api\Setups;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Setups\GroupsStoreRequest;
use App\Http\Requests\Api\Setups\GroupsUpdateRequest;
use App\Http\Resources\Api\Setups\GroupResource;
use App\Http\Responses\ApiResponse;
use App\Models\Group;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GroupsController extends Controller
{
    use HasPagination;

    public function index(Request $request): JsonResponse
    {
        $query = Group::query()
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
            GroupResource::class
        );
    }

    public function store(GroupsStoreRequest $request): JsonResponse
    {
        $group = Group::create($request->validated());
        $group->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::store(
            'Group created successfully',
            new GroupResource($group)
        );
    }

    public function show(Group $group): JsonResponse
    {
        $group->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::show(
            'Group retrieved successfully',
            new GroupResource($group)
        );
    }

    public function update(GroupsUpdateRequest $request, Group $group): JsonResponse
    {
        $group->update($request->validated());
        $group->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update(
            'Group updated successfully',
            new GroupResource($group)
        );
    }

    public function destroy(Group $group): JsonResponse
    {
        $group->delete();

        return ApiResponse::delete('Group deleted successfully');
    }

    public function trashed(Request $request): JsonResponse
    {
        $query = Group::onlyTrashed()
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
            GroupResource::class
        );
    }

    public function restore(int $id): JsonResponse
    {
        $group = Group::onlyTrashed()->findOrFail($id);
        $group->restore();
        $group->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update(
            'Group restored successfully',
            new GroupResource($group)
        );
    }

    public function forceDelete(int $id): JsonResponse
    {
        $group = Group::onlyTrashed()->findOrFail($id);
        $group->forceDelete();

        return ApiResponse::delete('Group permanently deleted successfully');
    }
}