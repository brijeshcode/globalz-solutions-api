<?php

namespace App\Http\Controllers\Api\Items;

use App\Exports\ItemCurrentPricesExport;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Jobs\RepairItemPriceJob;
use App\Models\Inventory\ItemPrice;
use App\Models\Inventory\ItemPriceHistory;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ItemCostHistoryController extends Controller
{
    use HasPagination;

    public function index(Request $request): JsonResponse
    {
        $itemId = $request->get('item_id');

        // Dispatch background job to sync item_prices with latest price history
        if ($itemId) {
            dispatch(new RepairItemPriceJob((int) $itemId));
        }

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

        $transformedItems = $rows->map(fn($row) => $this->transformRow($row));

        return ApiResponse::index('Item cost history retrieved successfully', $transformedItems->toArray());
    }

    public function exportCurrentPrices(Request $request): BinaryFileResponse
    {
        $search = $request->get('search');
        $itemId = $request->get('item_id');

        $rows = ItemPrice::with($this->exportEagerLoads())
            ->when($itemId, fn($q) => $q->where('item_id', $itemId))
            ->when($search, function ($q) use ($search) {
                $q->whereHas('item', function ($q) use ($search) {
                    $q->where('code', 'like', "%{$search}%")
                      ->orWhere('short_name', 'like', "%{$search}%");
                });
            })
            ->orderBy('effective_date', 'desc')
            ->get();

        $transformed = $rows->map(fn($row) => $this->transformCurrentPriceRow($row));

        $filename = 'item-current-prices-' . now()->format('Y-m-d') . '.xlsx';

        return Excel::download(new ItemCurrentPricesExport($transformed), $filename);
    }

    public function currentPrices(Request $request): JsonResponse
    {
        $search  = $request->get('search');
        $itemId  = $request->get('item_id');
        $perPage = $request->get('per_page', 50);

        $query = ItemPrice::with($this->currentPricesEagerLoads())
            ->when($itemId, fn($q) => $q->where('item_id', $itemId))
            ->when($search, function ($q) use ($search) {
                $q->whereHas('item', function ($q) use ($search) {
                    $q->where('code', 'like', "%{$search}%")
                      ->orWhere('short_name', 'like', "%{$search}%");
                });
            })
            ->orderBy('effective_date', 'desc');

        $rows = $query->paginate($perPage);

        $transformed = $rows->through(fn($row) => $this->transformCurrentPriceRow($row));

        return ApiResponse::paginated('Current item prices retrieved successfully', $transformed);
    }

    private function exportEagerLoads(): array
    {
        return [
            'item:id,code,short_name,description,item_unit_id',
            'item.itemUnit:id,name,short_name',
            'item.priceHistories' => fn($q) => $q
                ->whereIn('source_type', ['purchase', 'initial'])
                ->orderBy('id', 'desc')
                ->limit(1)
                ->with([
                    'source' => fn($morphTo) => $morphTo->morphWith([
                        \App\Models\Suppliers\Purchase::class => ['currency', 'items'],
                        \App\Models\Items\Item::class         => [],
                    ]),
                ]),
        ];
    }

    private function currentPricesEagerLoads(): array
    {
        return [
            'item:id,code,short_name,description,item_unit_id',
            'item.itemUnit:id,name,short_name',
            'item.priceHistories' => fn($q) => $q
                ->whereIn('source_type', ['purchase', 'initial'])
                ->orderBy('id', 'desc')
                ->limit(5)
                ->with([
                    'source' => fn($morphTo) => $morphTo->morphWith([
                        \App\Models\Suppliers\Purchase::class => ['currency', 'items'],
                        \App\Models\Items\Item::class         => [],
                    ]),
                ]),
        ];
    }

    private function transformCurrentPriceRow(ItemPrice $row): array
    {
        $itemId = $row->item_id;

        $history = ($row->item?->priceHistories ?? collect())->map(function ($h) use ($itemId) {
            if ($h->source_type === 'purchase' && $h->source) {
                $purchase = clone $h->source;
                $purchase->setRelation(
                    'items',
                    $h->source->items->filter(fn($i) => $i->item_id === $itemId)->values()
                );
                $h = clone $h;
                $h->setRelation('source', $purchase);
            }
            return $this->transformRow($h);
        });

        return [
            'item_id'        => $itemId,
            'item_code'      => $row->item?->code,
            'item_name'      => $row->item?->description,
            'unit'           => $row->item?->itemUnit ? [
                'id'         => $row->item->itemUnit->id,
                'name'       => $row->item->itemUnit->name,
                'short_name' => $row->item->itemUnit->short_name,
            ] : null,
            'price_usd'      => $row->price_usd,
            'effective_date' => $row->effective_date,
            'history'        => $history->values(),
        ];
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
