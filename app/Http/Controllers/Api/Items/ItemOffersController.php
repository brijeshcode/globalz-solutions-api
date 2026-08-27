<?php

namespace App\Http\Controllers\Api\Items;

use App\Helpers\RoleHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Items\ItemOffersStoreRequest;
use App\Http\Requests\Api\Items\ItemOffersUpdateRequest;
use App\Http\Resources\Api\Items\ItemOfferResource;
use App\Http\Responses\ApiResponse;
use App\Models\Items\ItemOffer;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItemOffersController extends Controller
{
    use HasPagination;

    public function index(Request $request): JsonResponse
    {
        $query = ItemOffer::query()
            ->with(['item:id,code,short_name,description', 'freeItem:id,code,short_name,description', 'createdBy:id,name', 'updatedBy:id,name'])
            ->sortable($request);

        if ($request->has('item_id')) {
            $query->where('item_id', $request->item_id);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->boolean('valid')) {
            $query->valid();
        }

        $query->fromDate($request->from_date, 'validity_date')
              ->toDate($request->to_date, 'validity_date');

        $offers = $this->applyPagination($query, $request);

        // Warehouse stock is only needed to confirm availability, so load it just
        // for active + valid offers (one batched query per relation, none if empty).
        $offers->getCollection()
            ->filter->isAvailable()
            ->load([
                'item.inventories:id,item_id,warehouse_id,quantity',
                'item.inventories.warehouse:id,name',
                'freeItem.inventories:id,item_id,warehouse_id,quantity',
                'freeItem.inventories.warehouse:id,name',
            ]);

        return ApiResponse::paginated(
            'Item offers retrieved successfully',
            $offers,
            ItemOfferResource::class
        );
    }

    public function store(ItemOffersStoreRequest $request): JsonResponse
    {
        $offer = ItemOffer::create($request->validated());

        $offer->load(['item:id,code,short_name', 'freeItem:id,code,short_name', 'createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::store(
            'Item offer created successfully',
            new ItemOfferResource($offer)
        );
    }

    public function show(ItemOffer $itemOffer): JsonResponse
    {
        $itemOffer->load(['item:id,code,short_name,description', 'freeItem:id,code,short_name,description', 'createdBy:id,name', 'updatedBy:id,name']);

        // Warehouse stock is only needed to confirm availability.
        if ($itemOffer->isAvailable()) {
            $itemOffer->load([
                'item.inventories:id,item_id,warehouse_id,quantity',
                'item.inventories.warehouse:id,name',
                'freeItem.inventories:id,item_id,warehouse_id,quantity',
                'freeItem.inventories.warehouse:id,name',
            ]);
        }

        return ApiResponse::show(
            'Item offer retrieved successfully',
            new ItemOfferResource($itemOffer)
        );
    }

    public function update(ItemOffersUpdateRequest $request, ItemOffer $itemOffer): JsonResponse
    {
        $itemOffer->update($request->validated());

        $itemOffer->load(['item:id,code,short_name', 'freeItem:id,code,short_name', 'createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update(
            'Item offer updated successfully',
            new ItemOfferResource($itemOffer)
        );
    }

    public function destroy(ItemOffer $itemOffer): JsonResponse
    {
        if (!RoleHelper::canAdmin()) {
            return ApiResponse::customError('Only admin users can delete item offers', 403);
        }

        $itemOffer->delete();

        return ApiResponse::delete('Item offer deleted successfully');
    }

    public function updateStatus(Request $request, ItemOffer $itemOffer): JsonResponse
    {
        if (!RoleHelper::canAdmin()) {
            return ApiResponse::customError('Only admin users can change offer status', 403);
        }

        $itemOffer->update(['is_active' => filter_var($request->status, FILTER_VALIDATE_BOOLEAN)]);

        return ApiResponse::update('Item offer status updated successfully');
    }

    public function trashed(Request $request): JsonResponse
    {
        if (!RoleHelper::canAdmin()) {
            return ApiResponse::customError('Only admin users can view trashed item offers', 403);
        }

        $query = ItemOffer::onlyTrashed()
            ->with(['item:id,code,short_name', 'freeItem:id,code,short_name', 'createdBy:id,name', 'updatedBy:id,name'])
            ->sortable($request);

        $offers = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Trashed item offers retrieved successfully',
            $offers,
            ItemOfferResource::class
        );
    }

    public function restore(int $id): JsonResponse
    {
        if (!RoleHelper::canSuperAdmin()) {
            return ApiResponse::customError('Only super admin users can restore item offers', 403);
        }

        $offer = ItemOffer::onlyTrashed()->findOrFail($id);
        $offer->restore();

        return ApiResponse::update('Item offer restored successfully');
    }

    public function forceDelete(int $id): JsonResponse
    {
        if (!RoleHelper::canSuperAdmin()) {
            return ApiResponse::customError('Only super admin users can permanently delete item offers', 403);
        }

        $offer = ItemOffer::onlyTrashed()->findOrFail($id);
        $offer->forceDelete();

        return ApiResponse::delete('Item offer permanently deleted successfully');
    }
}
