<?php

namespace App\Http\Controllers\Api\Setups;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Setups\ItemFamiliesStoreRequest;
use App\Http\Requests\Api\Setups\ItemFamiliesUpdateRequest;
use App\Http\Resources\Api\Setups\ItemFamilyResource;
use App\Http\Responses\ApiResponse;
use App\Models\Setups\ItemFamily;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItemFamiliesController extends Controller
{
    use HasPagination;

    public function index(Request $request): JsonResponse
    {
        $query = ItemFamily::query()
            ->with(['createdBy:id,name', 'updatedBy:id,name'])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $families = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Families retrieved successfully',
            $families,
            ItemFamilyResource::class
        );
    }

    public function store(ItemFamiliesStoreRequest $request): JsonResponse
    {
        $family = ItemFamily::create($request->validated());
        $family->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::store(
            'Family created successfully',
            new ItemFamilyResource($family)
        );
    }

    public function show(ItemFamily $family): JsonResponse
    {
        $family->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::show(
            'Family retrieved successfully',
            new ItemFamilyResource($family)
        );
    }

    public function update(ItemFamiliesUpdateRequest $request, ItemFamily $family): JsonResponse
    {
        $family->update($request->validated());
        $family->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update(
            'Family updated successfully',
            new ItemFamilyResource($family)
        );
    }

    public function destroy(ItemFamily $family): JsonResponse
    {
        $family->delete();

        return ApiResponse::delete('Family deleted successfully');
    }

    public function trashed(Request $request): JsonResponse
    {
        $query = ItemFamily::onlyTrashed()
            ->with(['createdBy:id,name', 'updatedBy:id,name'])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $families = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Trashed families retrieved successfully',
            $families,
            ItemFamilyResource::class
        );
    }

    public function restore(int $id): JsonResponse
    {
        $family = ItemFamily::onlyTrashed()->findOrFail($id);
        $family->restore();
        $family->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update(
            'Family restored successfully',
            new ItemFamilyResource($family)
        );
    }

    public function forceDelete(int $id): JsonResponse
    {
        $family = ItemFamily::onlyTrashed()->findOrFail($id);
        $family->forceDelete();

        return ApiResponse::delete('Family permanently deleted successfully');
    }
}