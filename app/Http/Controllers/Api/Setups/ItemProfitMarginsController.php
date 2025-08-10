<?php

namespace App\Http\Controllers\Api\Setups;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Setups\ItemProfitMarginsStoreRequest;
use App\Http\Requests\Api\Setups\ItemProfitMarginsUpdateRequest;
use App\Http\Resources\Api\Setups\ItemProfitMarginResource;
use App\Http\Responses\ApiResponse;
use App\Models\Setups\ItemProfitMargin;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItemProfitMarginsController extends Controller
{
    use HasPagination;

    public function index(Request $request): JsonResponse
    {
        $query = ItemProfitMargin::query()
            ->with(['createdBy:id,name', 'updatedBy:id,name'])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $margins = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Profit margins retrieved successfully',
            $margins,
            ItemProfitMarginResource::class
        );
    }

    public function store(ItemProfitMarginsStoreRequest $request): JsonResponse
    {
        $margin = ItemProfitMargin::create($request->validated());
        $margin->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::store(
            'Profit margin created successfully',
            new ItemProfitMarginResource($margin)
        );
    }

    public function show(ItemProfitMargin $margin): JsonResponse
    {
        $margin->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::show(
            'Profit margin retrieved successfully',
            new ItemProfitMarginResource($margin)
        );
    }

    public function update(ItemProfitMarginsUpdateRequest $request, ItemProfitMargin $margin): JsonResponse
    {
        $margin->update($request->validated());
        $margin->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update(
            'Profit margin updated successfully',
            new ItemProfitMarginResource($margin)
        );
    }

    public function destroy(ItemProfitMargin $margin): JsonResponse
    {
        $margin->delete();

        return ApiResponse::delete('Profit margin deleted successfully');
    }

    public function trashed(Request $request): JsonResponse
    {
        $query = ItemProfitMargin::onlyTrashed()
            ->with(['createdBy:id,name', 'updatedBy:id,name'])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('is_active')) {
            $query->where('is_active', $request->boolean('is_active'));
        }

        $margins = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Trashed profit margins retrieved successfully',
            $margins,
            ItemProfitMarginResource::class
        );
    }

    public function restore(int $id): JsonResponse
    {
        $margin = ItemProfitMargin::onlyTrashed()->findOrFail($id);
        $margin->restore();
        $margin->load(['createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update(
            'Profit margin restored successfully',
            new ItemProfitMarginResource($margin)
        );
    }

    public function forceDelete(int $id): JsonResponse
    {
        $margin = ItemProfitMargin::onlyTrashed()->findOrFail($id);
        $margin->forceDelete();

        return ApiResponse::delete('Profit margin permanently deleted successfully');
    }
}