<?php

namespace App\Http\Controllers\Api\Items;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Items\Item;
use App\Models\Suppliers\PurchaseItem;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItemCostHistoryController extends Controller
{
    use HasPagination;

    /**
     * Get item cost history from purchases
     *
     * Filters: item_id, search (item code/name), from_date, to_date
     * Columns: purchase date, purchase transaction code with prefix, item cost price, item_cost_price_usd
     */
    public function index(Request $request): JsonResponse
    {
        $itemId = $request->get('item_id');
        $search = $request->get('search');

        // Find item by ID or search term if provided
        $item = null;
        if ($itemId) {
            $item = Item::find($itemId);
        } elseif ($search) {
            $item = Item::where('code', $search)
                ->orWhere('description', 'like', "%{$search}%")
                ->first();
        }

        // Return empty if no item specified
        if (!$item) {
            if ($request->boolean('withPage')) {
                return ApiResponse::paginated(
                    'Item cost history retrieved successfully',
                    new \Illuminate\Pagination\LengthAwarePaginator([], 0, $this->getPerPage($request))
                );
            }
            return ApiResponse::index('Item cost history retrieved successfully', []);
        }

        // Build query with only necessary columns
        $query = PurchaseItem::query()
            ->join('purchases', 'purchase_items.purchase_id', '=', 'purchases.id')
            ->select(
                'purchase_items.id',
                'purchase_items.purchase_id',
                'purchase_items.price as cost_price',
                'purchase_items.cost_per_item_usd',
                'purchases.date as purchase_date',
                'purchases.prefix as purchase_prefix',
                'purchases.code as purchase_code'
            );

        // Apply item filter
        if ($item) {
            $query->where('purchase_items.item_id', $item->id);
        }

        // Apply date range filter
        if ($request->has('from_date')) {
            $query->where('purchases.date', '>=', $request->get('from_date'));
        }
        if ($request->has('to_date')) {
            $query->where('purchases.date', '<=', $request->get('to_date'));
        }

        // Order by purchase date descending (latest on top)
        $query->orderBy('purchases.date', 'desc');

        // Check if pagination is requested
        if ($request->boolean('withPage')) {
            $paginated = $query->paginate($this->getPerPage($request));

            // Transform paginated data
            $transformedData = $paginated->through(function ($purchaseItem) {
                return $this->transformPurchaseItem($purchaseItem);
            });

            return ApiResponse::paginated(
                'Item cost history retrieved successfully',
                $transformedData
            );
        }

        // Get all results
        $purchaseItems = $query->get();
        $transformedItems = $purchaseItems->map(fn($pi) => $this->transformPurchaseItem($pi))->toArray();

        return ApiResponse::index(
            'Item cost history retrieved successfully',
            $transformedItems
        );
    }

    /**
     * Transform purchase item for response
     */
    private function transformPurchaseItem($purchaseItem): array
    {
        return [
            'id' => $purchaseItem->id,
            'purchase_id' => $purchaseItem->purchase_id,
            'purchase_date' => $purchaseItem->purchase_date,
            'purchase_prefix' => $purchaseItem->purchase_prefix,
            'purchase_code' => $purchaseItem->purchase_code,
            'cost_price' => $purchaseItem->cost_price,
            'cost_price_usd' => $purchaseItem->cost_per_item_usd,
        ];
    }
}
