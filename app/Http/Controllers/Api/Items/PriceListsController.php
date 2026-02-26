<?php

namespace App\Http\Controllers\Api\Items;

use App\Helpers\RoleHelper;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Items\PriceListsStoreRequest;
use App\Http\Requests\Api\Items\PriceListsUpdateRequest;
use App\Http\Resources\Api\Items\PriceListResource;
use App\Http\Resources\Api\Items\PriceListItemResource;
use App\Http\Responses\ApiResponse;
use App\Models\Customers\Customer;
use App\Models\Items\Item;
use App\Models\Items\PriceList;
use App\Models\Items\PriceListItem;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class PriceListsController extends Controller
{
    use HasPagination;

    public function index(Request $request): JsonResponse
    {
        $query = PriceList::query()
            ->with(['items.item', 'createdBy:id,name', 'updatedBy:id,name'])
            ->withCount(['customersInv', 'customersInx'])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('code')) {
            $query->byCode($request->code);
        }

        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        if ($request->has('date_from')) {
            $query->fromDate($request->date_from, 'created_at');
        } 
        
        if ($request->has('date_to')) {
            $query->toDate( $request->date_to, 'created_at');
        }
        
        $priceLists = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Price lists retrieved successfully',
            $priceLists,
            PriceListResource::class
        );
    }

    public function store(PriceListsStoreRequest $request): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!RoleHelper::canAdmin()) {
            return ApiResponse::customError('Only admin users can create price lists', 403);
        }

        $data = $request->validated();

        $priceList = DB::transaction(function () use ($data, $user) {
            $items = $data['items'] ?? [];
            unset($data['items']);

            // Create the price list
            $priceList = PriceList::create([
                'code' => $data['code'],
                'description' => $data['description'],
                'item_count' => count($items),
                'note' => $data['note'] ?? null,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            // Create price list items
            foreach ($items as $itemData) {
                $priceListItemData = [
                    'price_list_id' => $priceList->id,
                    'item_code' => $itemData['item_code'],
                    'item_id' => $itemData['item_id'] ?? null,
                    'item_description' => $itemData['item_description'] ?? null,
                    'sell_price' => $itemData['sell_price'],
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ];

                // If item_id is provided, fetch item details
                if (isset($itemData['item_id'])) {
                    $item = Item::find($itemData['item_id']);
                    if ($item) {
                        $priceListItemData['item_code'] = $item->code;
                        $priceListItemData['item_description'] = $item->description;
                    }
                }

                PriceListItem::create($priceListItemData);
            }

            return $priceList;
        });

        $priceList->load(['items.item', 'createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::store(
            'Price list created successfully',
            new PriceListResource($priceList)
        );
    }

    public function show(PriceList $priceList): JsonResponse
    {
        $priceList->load([
            'items',
            'createdBy:id,name',
            'updatedBy:id,name'
        ]);

        return ApiResponse::show(
            'Price list retrieved successfully',
            new PriceListResource($priceList)
        );
    }

    public function update(PriceListsUpdateRequest $request, PriceList $priceList): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!RoleHelper::canAdmin()) {
            return ApiResponse::customError('Only admin users can update price lists', 403);
        }

        $data = $request->validated();

        DB::transaction(function () use ($data, $priceList, $user) {
            // Update price list basic info
            $priceList->update([
                'code' => $data['code'] ?? $priceList->code,
                'description' => $data['description'] ?? $priceList->description,
                'note' => $data['note'] ?? $priceList->note,
                'updated_by' => $user->id,
            ]);

            // Handle items if provided
            if (isset($data['items'])) {
                $items = $data['items'];

                // Get existing item IDs from the request
                $requestItemIds = collect($items)
                    ->pluck('id')
                    ->filter()
                    ->values()
                    ->all();

                // Delete items that are not in the request
                $priceList->items()
                    ->whereNotIn('id', $requestItemIds)
                    ->delete();

                // Update or create items
                foreach ($items as $itemData) {
                    $priceListItemData = [
                        'price_list_id' => $priceList->id,
                        'item_code' => $itemData['item_code'],
                        'item_id' => $itemData['item_id'] ?? null,
                        'item_description' => $itemData['item_description'] ?? null,
                        'sell_price' => $itemData['sell_price'],
                        'updated_by' => $user->id,
                    ];

                    // If item_id is provided, fetch item details
                    if (isset($itemData['item_id'])) {
                        $item = Item::find($itemData['item_id']);
                        if ($item) {
                            $priceListItemData['item_code'] = $item->code;
                            $priceListItemData['item_description'] = $item->description;
                        }
                    }

                    if (isset($itemData['id']) && $itemData['id']) {
                        // Update existing item
                        $priceListItem = PriceListItem::find($itemData['id']);
                        if ($priceListItem && $priceListItem->price_list_id === $priceList->id) {
                            $priceListItem->update($priceListItemData);
                        }
                    } else {
                        // Create new item
                        $priceListItemData['created_by'] = $user->id;
                        PriceListItem::create($priceListItemData);
                    }
                }

                // Update item count
                $priceList->updateItemCount();
            }
        });

        $priceList->load(['items.item', 'createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update(
            'Price list updated successfully',
            new PriceListResource($priceList)
        );
    }

    public function destroy(PriceList $priceList): JsonResponse
    {
        if (!RoleHelper::canAdmin()) {
            return ApiResponse::customError('Only admin users can delete price lists', 403);
        }

        $priceList->delete();

        return ApiResponse::delete('Price list deleted successfully');
    }

    public function trashed(Request $request): JsonResponse
    {
        $query = PriceList::onlyTrashed()
            ->with(['items.item', 'createdBy:id,name', 'updatedBy:id,name'])
            ->searchable($request)
            ->sortable($request);

        if ($request->has('code')) {
            $query->byCode($request->code);
        }

        $priceLists = $this->applyPagination($query, $request);

        return ApiResponse::paginated(
            'Trashed price lists retrieved successfully',
            $priceLists,
            PriceListResource::class
        );
    }

    public function restore(int $id): JsonResponse
    {
        if (!RoleHelper::canSuperAdmin()) {
            return ApiResponse::customError('Only admin users can restore price lists', 403);
        }

        $priceList = PriceList::onlyTrashed()->findOrFail($id);

        $priceList->restore();
        $priceList->items()->withTrashed()->restore();

        $priceList->load(['items.item', 'createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update(
            'Price list restored successfully',
            new PriceListResource($priceList)
        );
    }

    public function forceDelete(int $id): JsonResponse
    {
        if (!RoleHelper::canSuperAdmin()) {
            return ApiResponse::customError('Only admin users can permanently delete price lists', 403);
        }

        $priceList = PriceList::onlyTrashed()->findOrFail($id);

        $priceList->items()->withTrashed()->forceDelete();
        $priceList->forceDelete();

        return ApiResponse::delete('Price list permanently deleted successfully');
    }

    public function stats(Request $request): JsonResponse
    {
        $query = PriceList::query();

        $stats = [
            'total_price_lists' => (clone $query)->count(),
            'trashed_price_lists' => (clone $query)->onlyTrashed()->count(),
            'total_items' => (clone $query)->sum('item_count'),
            'average_items_per_list' => (clone $query)->avg('item_count'),
            'recent_price_lists' => (clone $query)
                ->with(['createdBy:id,name'])
                ->orderBy('created_at', 'desc')
                ->limit(5)
                ->get(),
        ];

        return ApiResponse::show('Price list statistics retrieved successfully', $stats);
    }

    public function duplicate(PriceList $priceList): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!RoleHelper::canSuperAdmin()) {
            return ApiResponse::customError('Only admin users can duplicate price lists', 403);
        }

        $newPriceList = DB::transaction(function () use ($priceList, $user) {
            // Create a new price list with duplicated data
            $newPriceList = PriceList::create([
                'code' => $priceList->code . '-COPY',
                'description' => $priceList->description . ' (Copy)',
                'item_count' => $priceList->item_count,
                'note' => $priceList->note,
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ]);

            // Duplicate all items
            foreach ($priceList->items as $item) {
                PriceListItem::create([
                    'price_list_id' => $newPriceList->id,
                    'item_code' => $item->item_code,
                    'item_id' => $item->item_id,
                    'item_description' => $item->item_description,
                    'sell_price' => $item->sell_price,
                    'created_by' => $user->id,
                    'updated_by' => $user->id,
                ]);
            }

            return $newPriceList;
        });

        $newPriceList->load(['items.item', 'createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::store(
            'Price list duplicated successfully',
            new PriceListResource($newPriceList)
        );
    }

    /**
     * Get all items of a given price list
     */
    public function getItems(PriceList $priceList): JsonResponse
    {
        $items = $priceList->items()
            ->with(['item', 'createdBy:id,name', 'updatedBy:id,name'])
            ->get();

        return ApiResponse::show(
            'Price list items retrieved successfully',
            PriceListItemResource::collection($items)
        );
    }

    /**
     * Add a new item to an existing price list
     */
    public function addItem(Request $request, PriceList $priceList): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!RoleHelper::canAdmin()) {
            return ApiResponse::customError('Only admin users can add items to price lists', 403);
        }
   
        $validated = $request->validate([
            'item_code' => 'required|string|max:255',
            'item_id' => 'nullable|exists:items,id',
            'item_description' => 'nullable|string',
            'sell_price' => 'required|numeric|min:0',
        ]);

        $priceListItem = DB::transaction(function () use ($validated, $priceList, $user) {
            $priceListItemData = [
                'price_list_id' => $priceList->id,
                'item_code' => $validated['item_code'],
                'item_id' => $validated['item_id'] ?? null,
                'item_description' => $validated['item_description'] ?? null,
                'sell_price' => $validated['sell_price'],
                'created_by' => $user->id,
                'updated_by' => $user->id,
            ];

            // If item_id is provided, fetch item details
            if (isset($validated['item_id'])) {
                $item = Item::find($validated['item_id']);
                if ($item) {
                    $priceListItemData['item_code'] = $item->code;
                    $priceListItemData['item_description'] = $item->description;
                }
            }

            $priceListItem = PriceListItem::create($priceListItemData);

            // Update item count on the price list
            $priceList->updateItemCount();

            return $priceListItem;
        });

        $priceListItem->load(['item', 'createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::store(
            'Item added to price list successfully',
            new PriceListItemResource($priceListItem)
        );
    }

    /**
     * Update a specific price list item
     */
    public function updateItem(Request $request, PriceList $priceList, PriceListItem $priceListItem): JsonResponse
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();

        if (!RoleHelper::canAdmin()) {
            return ApiResponse::customError('Only admin users can update price list items', 403);
        }

        // Verify the item belongs to the price list
        if ($priceListItem->price_list_id !== $priceList->id) {
            return ApiResponse::customError('Price list item does not belong to this price list', 404);
        }

        $validated = $request->validate([
            'item_code' => 'sometimes|required|string|max:255',
            'item_id' => 'nullable|exists:items,id',
            'item_description' => 'nullable|string',
            'sell_price' => 'sometimes|required|numeric|min:0',
        ]);

        DB::transaction(function () use ($validated, $priceListItem, $user) {
            $updateData = [
                'updated_by' => $user->id,
            ];

            // Only update fields that are provided
            if (isset($validated['item_code'])) {
                $updateData['item_code'] = $validated['item_code'];
            }

            if (isset($validated['sell_price'])) {
                $updateData['sell_price'] = $validated['sell_price'];
            }

            if (isset($validated['item_description'])) {
                $updateData['item_description'] = $validated['item_description'];
            }

            // If item_id is provided, fetch and update item details
            if (isset($validated['item_id'])) {
                $updateData['item_id'] = $validated['item_id'];

                if ($validated['item_id']) {
                    $item = Item::find($validated['item_id']);
                    if ($item) {
                        $updateData['item_code'] = $item->code;
                        $updateData['item_description'] = $item->description;
                    }
                }
            }

            $priceListItem->update($updateData);
        });

        $priceListItem->load(['item', 'createdBy:id,name', 'updatedBy:id,name']);

        return ApiResponse::update(
            'Price list item updated successfully',
            new PriceListItemResource($priceListItem)
        );
    }

    /**
     * Delete a specific price list item
     */
    public function deleteItem(PriceListItem $priceListItem): JsonResponse
    {
        if (!RoleHelper::canAdmin()) {
            return ApiResponse::customError('Only admin users can delete price list items', 403);
        }

        DB::transaction(function () use ($priceListItem) {
            $priceList = PriceList::find($priceListItem->price_list_id);
            $priceListItem->delete();
            
            // Update item count on the price list
            $priceList->updateItemCount();
        });

        return ApiResponse::delete('Price list item deleted successfully');
    }

    public function setDefault(Request $request): JsonResponse
    {
        $request->validate([
            'price_list_id_inv' => 'nullable|integer|exists:price_lists,id',
            'price_list_id_inx' => 'nullable|integer|exists:price_lists,id',
        ]);

        if (!$request->price_list_id_inv && !$request->price_list_id_inx) {
            return ApiResponse::customError('At least one price list ID is required', 422);
        }

        if ($request->price_list_id_inv) {
            PriceList::where('is_default_inv', true)->update(['is_default_inv' => false]);
            PriceList::where('id', $request->price_list_id_inv)->update(['is_default_inv' => true]);
        }

        if ($request->price_list_id_inx) {
            PriceList::where('is_default_inx', true)->update(['is_default_inx' => false]);
            PriceList::where('id', $request->price_list_id_inx)->update(['is_default_inx' => true]);
        }

        return ApiResponse::update(
            'PriceList defaults set',
            [
                'default_inv' => $request->price_list_id_inv ? new PriceListResource(PriceList::find($request->price_list_id_inv)) : null,
                'default_inx' => $request->price_list_id_inx ? new PriceListResource(PriceList::find($request->price_list_id_inx)) : null,
            ]
        );
    }

    public function changeCustomerPriceList(Request $request): JsonResponse
    {
        $request->validate([
            'old_price_list_id_inv' => 'nullable|integer',
            'new_price_list_id_inv' => 'nullable|integer|required_with:old_price_list_id_inv',
            'old_price_list_id_inx' => 'nullable|integer',
            'new_price_list_id_inx' => 'nullable|integer|required_with:old_price_list_id_inx',
        ]);

        $hasInvPair = $request->old_price_list_id_inv && $request->new_price_list_id_inv;
        $hasInxPair = $request->old_price_list_id_inx && $request->new_price_list_id_inx;

        if (!$hasInvPair && !$hasInxPair) {
            return ApiResponse::customError('At least one complete price list pair (old and new) is required', 422);
        }

        if ($request->old_price_list_id_inv && $request->new_price_list_id_inv) {
            Customer::where('price_list_id_INV', $request->old_price_list_id_inv)
                ->update(['price_list_id_INV' => $request->new_price_list_id_inv]);
        }

        if ($request->old_price_list_id_inx && $request->new_price_list_id_inx) {
            Customer::where('price_list_id_INX', $request->old_price_list_id_inx)
                ->update(['price_list_id_INX' => $request->new_price_list_id_inx]);
        }

        return ApiResponse::update('Customer price lists updated successfully');
    }

    public function updateStatus(Request $request, PriceList $priceList)
    {
        $status = $request->status === 'active';

        if ($priceList->customersInv()->exists() || $priceList->customersInx()->exists()) {
            return ApiResponse::error('Cannot deactivate price list that has customers assigned to it.', 422);
        }

        $priceList->update(['is_active' => $status]);

        return ApiResponse::update('Price list status updated successfully');
    }
}
