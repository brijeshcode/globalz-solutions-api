<?php

namespace App\Http\Controllers\Api\Items;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Inventory\ItemPriceHistory;
use App\Models\Items\Item;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ItemCostHistoryController extends Controller
{
    use HasPagination;

    public function index(Request $request): JsonResponse
    {
        $itemId = $request->get('item_id');
        $search = $request->get('search');

        $rows = ItemPriceHistory::where('item_id', $itemId)
            ->whereIn('source_type', ['purchase', 'initial'])
            ->orderBy('id', 'desc')
            ->with([
                'source' => fn($morphTo) => $morphTo->morphWith([
                    \App\Models\Suppliers\Purchase::class => [
                        'currency',
                        'items' => fn($q) => $q->where('item_id', $itemId),
                    ],
                    \App\Models\Items\Item::class => [],
                ]),
            ])
            ->get();

        // return ApiResponse::index('Item cost history retrieved successfully', $rows->toArray());

        $transformedItems = $rows->map(fn($row) => $this->transformRow($row));

        

        return ApiResponse::index('Item cost history retrieved successfully', $transformedItems->toArray());
    }

    private function transformRow(ItemPriceHistory $history): array
    {
        $isPurchase = $history->source_type === 'purchase';

        $purchase     = $isPurchase ? $history->source : null;
        $purchaseItem = $purchase?->items->first();
        $currency     = $purchase?->currency;

        return [
            'id'              => $history->id,
            'source_type'     => $history->source_type,
            'effective_date'  => $history->effective_date,
            'source_id'       => $purchase?->id,
            'source_date'     => $history->created_at,
            'source_prefix'   => $purchase?->prefix,
            'source_code'     => $isPurchase ? $purchase?->code : $history->source_type,
            'cost_price'      => $isPurchase ? $purchaseItem?->price : $history->price_usd,
            'price_usd'       => $history->price_usd,
            'discount_percent' => $isPurchase ? $purchaseItem?->discount_percent : 0,
            'currency_rate'   => $isPurchase ? $purchase?->currency_rate : 1,
            'exp_share'       => $isPurchase && $purchaseItem?->quantity > 0
                                    ? round($purchaseItem->total_expense_usd / $purchaseItem->quantity, 4)
                                    : 0,
            'exp_share_total' => $isPurchase ? $purchaseItem?->total_expense_usd : 0,
            'exp_pct'         => $isPurchase && $purchaseItem?->final_total_cost_usd > 0
                                    ? round(($purchaseItem->total_expense_usd / $purchaseItem->final_total_cost_usd) * 100, 2)
                                    : 0,
            'remark'          => $history->note,
            'currency'        => $currency ? [
                'id'                 => $currency->id,
                'symbol'             => $currency->symbol,
                'symbol_position'    => $currency->symbol_position,
                'decimal_places'     => $currency->decimal_places,
                'decimal_separator'  => $currency->decimal_separator,
                'thousand_separator' => $currency->thousand_separator,
            ] : null,
        ];
    }
}
