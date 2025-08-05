<?php

namespace App\Http\Controllers\Api\Setups;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Setups\CategoriesStoreRequest;
use App\Http\Requests\Api\Setups\CategoriesUpdateRequest;
use App\Http\Resources\Api\Setups\CategoryResource;
use App\Http\Responses\ApiResponse;
use App\Models\Category;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoriesController extends Controller
{
    use HasPagination;

    public function index(Request $request): JsonResponse
    {
        $query = Category::query()
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
            CategoryResource::class
        );
    }

    public function store(CategoriesStoreRequest $request): JsonResponse
    {
        $category = Category::create($request->validated());
        $category->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::store(
            'Category created successfully',
            new CategoryResource($category)
        );
    }

    public function show(Category $category): JsonResponse
    {
        $category->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::show(
            'Category retrieved successfully',
            new CategoryResource($category)
        );
    }

    public function update(CategoriesUpdateRequest $request, Category $category): JsonResponse
    {
        $category->update($request->validated());
        $category->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update(
            'Category updated successfully',
            new CategoryResource($category)
        );
    }

    public function destroy(Category $category): JsonResponse
    {
        $category->delete();

        return ApiResponse::delete('Category deleted successfully');
    }

    public function trashed(Request $request): JsonResponse
    {
        $query = Category::onlyTrashed()
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
            CategoryResource::class
        );
    }

    public function restore(int $id): JsonResponse
    {
        $category = Category::onlyTrashed()->findOrFail($id);
        $category->restore();
        $category->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update(
            'Category restored successfully',
            new CategoryResource($category)
        );
    }

    public function forceDelete(int $id): JsonResponse
    {
        $category = Category::onlyTrashed()->findOrFail($id);
        $category->forceDelete();

        return ApiResponse::delete('Category permanently deleted successfully');
    }
}