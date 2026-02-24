<?php

namespace App\Http\Controllers\Api\Items;

use App\Helpers\RoleHelper;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Items\PriceListBulkUpdate;
use App\Models\Items\PriceListBulkUpdateItem;
use App\Models\Items\PriceList;
use App\Models\Items\PriceListItem;
use App\Http\Resources\Api\Items\PriceListResource;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PriceListBulkUpdateController extends Controller
{
    use HasPagination;

    public function index(Request $request): JsonResponse
    {
        $query = PriceListBulkUpdate::query()
            ->with(['createdBy:id,name', 'updatedBy:id,name'])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('from_date')) {
            $query->fromDate($request->from_date, 'date');
        }

        if ($request->has('to_date')) {
            $query->toDate($request->to_date, 'date');
        }

        $bulkUpdates = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Bulk updates retrieved successfully',
            $bulkUpdates
        );
    }

    public function show(PriceListBulkUpdate $bulkUpdate): JsonResponse
    {
        $bulkUpdate->load([
            'items.priceList:id,code,description',
            'createdBy:id,name',
            'updatedBy:id,name',
        ]);

        return ApiResponse::show(
            'Bulk update retrieved successfully',
            $bulkUpdate
        );
    }

    public function store(Request $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!RoleHelper::canAdmin()) {
            return ApiResponse::customError('Only admin users can perform bulk updates', 403);
        }

        $validated = $request->validate([
            'date' => 'required|date',
            'note' => 'nullable|string',
            'filters' => 'nullable|array',
            'items' => 'required|array|min:1',
            'items.*.price_list_item_id' => 'required|exists:price_list_items,id',
            'items.*.new_price' => 'required|numeric|min:0',
        ]);

        $bulkUpdate = DB::transaction(function () use ($validated, $user) {
            $bulkUpdate = PriceListBulkUpdate::create([
                'date' => $validated['date'],
                'note' => $validated['note'] ?? null,
                'filters' => $validated['filters'] ?? null,
                'item_count' => 0,
                'price_list_count' => 0,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            $itemCount = 0;
            $priceListIds = [];

            foreach ($validated['items'] as $itemData) {
                $priceListItem = PriceListItem::find($itemData['price_list_item_id']);

                if (!$priceListItem) {
                    continue;
                }

                $oldPrice = $priceListItem->sell_price;
                $newPrice = $itemData['new_price'];

                // Skip if price hasn't changed
                if ((float) $oldPrice === (float) $newPrice) {
                    continue;
                }

                // Record the change
                PriceListBulkUpdateItem::create([
                    'bulk_update_id' => $bulkUpdate->id,
                    'price_list_item_id' => $priceListItem->id,
                    'price_list_id' => $priceListItem->price_list_id,
                    'item_id' => $priceListItem->item_id,
                    'item_code' => $priceListItem->item_code,
                    'item_description' => $priceListItem->item_description,
                    'old_price' => $oldPrice,
                    'new_price' => $newPrice,
                ]);

                // Update the actual price
                $priceListItem->update([
                    'sell_price' => $newPrice,
                    'updated_by' => $user->id,
                ]);

                $itemCount++;
                $priceListIds[] = $priceListItem->price_list_id;
            }

            $bulkUpdate->updateQuietly([
                'item_count' => $itemCount,
                'price_list_count' => count(array_unique($priceListIds)),
            ]);

            return $bulkUpdate;
        });

        $bulkUpdate->load(['items', 'createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::store(
            'Bulk update completed successfully',
            $bulkUpdate
        );
    }

    public function update(Request $request, PriceListBulkUpdate $bulkUpdate): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!RoleHelper::canAdmin()) {
            return ApiResponse::customError('Only admin users can edit bulk updates', 403);
        }

        $validated = $request->validate([
            'date' => 'sometimes|required|date',
            'note' => 'nullable|string',
        ]);

        $bulkUpdate->update([
            ...$validated,
            'updated_by' => $user->id,
        ]);

        $bulkUpdate->load(['items', 'createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update(
            'Bulk update record updated successfully',
            $bulkUpdate
        );
    }

    public function destroy(PriceListBulkUpdate $bulkUpdate): JsonResponse
    {
        if (!RoleHelper::canAdmin()) {
            return ApiResponse::customError('Only admin users can delete bulk update records', 403);
        }

        $bulkUpdate->delete();

        return ApiResponse::delete('Bulk update record deleted successfully');
    }

    public function filterByItems(Request $request): JsonResponse
    {
        $query = PriceListItem::query()->with(['item', 'priceList']);

        // Filter by active price lists only
        if ($request->boolean('active_only')) {
            $query->whereHas('priceList', function ($q) {
                $q->where('is_active', true);
            });
        }

        // Direct filter on price_list_items
        if ($request->has('item_id')) {
            $query->where('item_id', $request->item_id);
        }

        // Filters that go through the related item
        if ($request->has('supplier_id') || $request->has('item_type_id') || $request->has('item_family_id') || $request->has('item_group_id') || $request->has('item_category_id')) {
            $query->whereHas('item', function ($q) use ($request) {
                if ($request->has('supplier_id')) {
                    $q->where('supplier_id', $request->supplier_id);
                }
                if ($request->has('item_type_id')) {
                    $q->where('item_type_id', $request->item_type_id);
                }
                if ($request->has('item_family_id')) {
                    $q->where('item_family_id', $request->item_family_id);
                }
                if ($request->has('item_group_id')) {
                    $q->where('item_group_id', $request->item_group_id);
                }
                if ($request->has('item_category_id')) {
                    $q->where('item_category_id', $request->item_category_id);
                }
            });
        }

        $filteredItems = $query->get();

        // Group by price list and return price lists with their matching items
        $priceListIds = $filteredItems->pluck('price_list_id')->unique();

        $priceLists = PriceList::whereIn('id', $priceListIds)
            ->get()
            ->map(function ($priceList) use ($filteredItems) {
                $priceList->setRelation('items', $filteredItems->where('price_list_id', $priceList->id)->values());
                return $priceList;
            });

        return ApiResponse::show(
            'Price lists retrieved successfully',
            PriceListResource::collection($priceLists)
        );
    }
}
