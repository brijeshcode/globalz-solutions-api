<?php

namespace App\Http\Controllers\Api\Setups;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Setups\ItemTypesStoreRequest;
use App\Http\Requests\Api\Setups\ItemTypesUpdateRequest;
use App\Http\Resources\Api\Setups\ItemTypeResource;
use App\Http\Responses\ApiResponse;
use App\Models\Setups\ItemType;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItemTypesController extends Controller
{
    use HasPagination;

    public function index(Request $request): JsonResponse
    {
        $query = ItemType::query()
            ->with(['createdBy:id,name', 'updatedBy:id,name'])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $types = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Types retrieved successfully',
            $types,
            ItemTypeResource::class
        );
    }

    public function store(ItemTypesStoreRequest $request): JsonResponse
    {
        $type = ItemType::create($request->validated());
        $type->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::store(
            'Type created successfully',
            new ItemTypeResource($type)
        );
    }

    public function show(ItemType $type): JsonResponse
    {
        $type->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::show(
            'Type retrieved successfully',
            new ItemTypeResource($type)
        );
    }

    public function update(ItemTypesUpdateRequest $request, ItemType $type): JsonResponse
    {
        $type->update($request->validated());
        $type->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update(
            'Type updated successfully',
            new ItemTypeResource($type)
        );
    }

    public function destroy(ItemType $type): JsonResponse
    {
        $type->delete();

        return ApiResponse::delete('Type deleted successfully');
    }

    public function trashed(Request $request): JsonResponse
    {
        $query = ItemType::onlyTrashed()
            ->with(['createdBy:id,name', 'updatedBy:id,name'])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $types = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Trashed types retrieved successfully',
            $types,
            ItemTypeResource::class
        );
    }

    public function restore(int $id): JsonResponse
    {
        $type = ItemType::onlyTrashed()->findOrFail($id);
        $type->restore();
        $type->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update(
            'Type restored successfully',
            new ItemTypeResource($type)
        );
    }

    public function forceDelete(int $id): JsonResponse
    {
        $type = ItemType::onlyTrashed()->findOrFail($id);
        $type->forceDelete();

        return ApiResponse::delete('Type permanently deleted successfully');
    }
}