<?php

namespace App\Http\Controllers\Api\Setups;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Setups\ItemCategoriesStoreRequest;
use App\Http\Requests\Api\Setups\ItemCategoriesUpdateRequest;
use App\Http\Resources\Api\Setups\ItemCategoryResource;
use App\Http\Responses\ApiResponse;
use App\Models\Setups\ItemCategory;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItemCategoriesController extends Controller
{
    use HasPagination;

    public function index(Request $request): JsonResponse
    {
        $query = ItemCategory::query()
            ->with(['createdBy:id,name', 'updatedBy:id,name'])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $categories = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Categories retrieved successfully',
            $categories,
            ItemCategoryResource::class
        );
    }

    public function store(ItemCategoriesStoreRequest $request): JsonResponse
    {
        $category = ItemCategory::create($request->validated());
        $category->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::store(
            'Category created successfully',
            new ItemCategoryResource($category)
        );
    }

    public function show(ItemCategory $category): JsonResponse
    {
        $category->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::show(
            'Category retrieved successfully',
            new ItemCategoryResource($category)
        );
    }

    public function update(ItemCategoriesUpdateRequest $request, ItemCategory $category): JsonResponse
    {
        $category->update($request->validated());
        $category->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update(
            'Category updated successfully',
            new ItemCategoryResource($category)
        );
    }

    public function destroy(ItemCategory $category): JsonResponse
    {
        $category->delete();

        return ApiResponse::delete('Category deleted successfully');
    }

    public function trashed(Request $request): JsonResponse
    {
        $query = ItemCategory::onlyTrashed()
            ->with(['createdBy:id,name', 'updatedBy:id,name'])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $categories = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Trashed categories retrieved successfully',
            $categories,
            ItemCategoryResource::class
        );
    }

    public function restore(int $id): JsonResponse
    {
        $category = ItemCategory::onlyTrashed()->findOrFail($id);
        $category->restore();
        $category->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update(
            'Category restored successfully',
            new ItemCategoryResource($category)
        );
    }

    public function forceDelete(int $id): JsonResponse
    {
        $category = ItemCategory::onlyTrashed()->findOrFail($id);
        $category->forceDelete();

        return ApiResponse::delete('Category permanently deleted successfully');
    }
}