<?php

namespace App\Http\Controllers\Api\Setups;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Setups\BrandsStoreRequest;
use App\Http\Requests\Api\Setups\BrandsUpdateRequest;
use App\Http\Resources\Api\Setups\BrandResource;
use App\Http\Responses\ApiResponse;
use App\Models\Brand;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BrandsController extends Controller
{
    use HasPagination;

    public function index(Request $request): JsonResponse
    {
        $query = Brand::query()
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
            BrandResource::class
        );
    }

    public function store(BrandsStoreRequest $request): JsonResponse
    {
        $brand = Brand::create($request->validated());
        $brand->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::store(
            'Brand created successfully',
            new BrandResource($brand)
        );
    }

    public function show(Brand $brand): JsonResponse
    {
        $brand->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::show(
            'Brand retrieved successfully',
            new BrandResource($brand)
        );
    }

    public function update(BrandsUpdateRequest $request, Brand $brand): JsonResponse
    {
        $brand->update($request->validated());
        $brand->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update(
            'Brand updated successfully',
            new BrandResource($brand)
        );
    }

    public function destroy(Brand $brand): JsonResponse
    {
        $brand->delete();

        return ApiResponse::delete('Brand deleted successfully');
    }

    public function trashed(Request $request): JsonResponse
    {
        $query = Brand::onlyTrashed()
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
            BrandResource::class
        );
    }

    public function restore(int $id): JsonResponse
    {
        $brand = Brand::onlyTrashed()->findOrFail($id);
        $brand->restore();
        $brand->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update(
            'Brand restored successfully',
            new BrandResource($brand)
        );
    }

    public function forceDelete(int $id): JsonResponse
    {
        $brand = Brand::onlyTrashed()->findOrFail($id);
        $brand->forceDelete();

        return ApiResponse::delete('Brand permanently deleted successfully');
    }
}