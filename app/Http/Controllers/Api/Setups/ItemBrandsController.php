<?php

namespace App\Http\Controllers\Api\Setups;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Setups\ItemBrandsStoreRequest;
use App\Http\Requests\Api\Setups\ItemBrandsUpdateRequest;
use App\Http\Resources\Api\Setups\ItemBrandResource;
use App\Http\Responses\ApiResponse;
use App\Models\Setups\ItemBrand;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItemBrandsController extends Controller
{
    use HasPagination;

    public function index(Request $request): JsonResponse
    {
        $query = ItemBrand::query()
            ->with(['createdBy:id,name', 'updatedBy:id,name'])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $brands = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Brands retrieved successfully',
            $brands,
            ItemBrandResource::class
        );
    }

    public function store(ItemBrandsStoreRequest $request): JsonResponse
    {
        $brand = ItemBrand::create($request->validated());
        $brand->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::store(
            'Brand created successfully',
            new ItemBrandResource($brand)
        );
    }

    public function show(ItemBrand $brand): JsonResponse
    {
        $brand->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::show(
            'Brand retrieved successfully',
            new ItemBrandResource($brand)
        );
    }

    public function update(ItemBrandsUpdateRequest $request, ItemBrand $brand): JsonResponse
    {
        $brand->update($request->validated());
        $brand->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update(
            'Brand updated successfully',
            new ItemBrandResource($brand)
        );
    }

    public function destroy(ItemBrand $brand): JsonResponse
    {
        $brand->delete();

        return ApiResponse::delete('Brand deleted successfully');
    }

    public function trashed(Request $request): JsonResponse
    {
        $query = ItemBrand::onlyTrashed()
            ->with(['createdBy:id,name', 'updatedBy:id,name'])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $brands = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Trashed brands retrieved successfully',
            $brands,
            ItemBrandResource::class
        );
    }

    public function restore(int $id): JsonResponse
    {
        $brand = ItemBrand::onlyTrashed()->findOrFail($id);
        $brand->restore();
        $brand->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update(
            'Brand restored successfully',
            new ItemBrandResource($brand)
        );
    }

    public function forceDelete(int $id): JsonResponse
    {
        $brand = ItemBrand::onlyTrashed()->findOrFail($id);
        $brand->forceDelete();

        return ApiResponse::delete('Brand permanently deleted successfully');
    }
}