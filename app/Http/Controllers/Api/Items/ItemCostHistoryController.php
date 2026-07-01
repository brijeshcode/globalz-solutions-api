<?php

namespace App\Http\Controllers\Api\Items;

use App\Exports\ItemCurrentPricesExport;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Inventory\ItemPrice;
use App\Models\Inventory\ItemPriceHistory;
use App\Services\Inventory\PriceService;
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
        $rows = ItemPriceHistory::where('item_id', $request->get('item_id'))
            ->whereIn('source_type', ['purchase_item', 'initial'])
            ->orderBy('id', 'desc')
            ->with(['purchaseItemSource.purchase.currency'])
            ->get();

        return ApiResponse::index('Item cost history retrieved successfully',
            $rows->map(fn($row) => $this->transformRow($row))->toArray()
        );
    }

    public function currentPrices(Request $request): JsonResponse
    {
        $rows = ItemPrice::with($this->priceEagerLoads())
            ->when($request->get('item_id'), fn($q, $id) => $q->where('item_id', $id))
            ->when($request->get('search'), fn($q, $s) => $q->whereHas('item', fn($q) =>
                $q->where('code', 'like', "%{$s}%")->orWhere('short_name', 'like', "%{$s}%")
            ))
            ->orderBy('effective_date', 'desc')
            ->paginate($request->get('per_page', 50));

        // When ?verify=1, run the audit and attach a price_check to each row showing
        // whether the stored price matches the recomputed price.
        $auditIndex = null;
        if ($request->boolean('verify')) {
            $tolerance  = (float) $request->input('tolerance', 0.0);
            $audit      = PriceService::auditItemPrices($tolerance);
            $auditIndex = collect($audit['changes'])
                ->concat($audit['missing'])
                ->keyBy('item_id');
        }

        return ApiResponse::paginated('Current item prices retrieved successfully',
            $rows->through(fn($row) => $this->transformCurrentPriceRow($row, $auditIndex))
        );
    }

    public function exportCurrentPrices(Request $request): BinaryFileResponse
    {
        $rows = ItemPrice::with($this->priceEagerLoads())
            ->when($request->get('item_id'), fn($q, $id) => $q->where('item_id', $id))
            ->when($request->get('search'), fn($q, $s) => $q->whereHas('item', fn($q) =>
                $q->where('code', 'like', "%{$s}%")->orWhere('short_name', 'like', "%{$s}%")
            ))
            ->orderBy('effective_date', 'desc')
            ->get();

        return Excel::download(
            new ItemCurrentPricesExport($rows->map(fn($row) => $this->transformCurrentPriceRow($row))),
            'items-cost-list-' . now()->format('Y-m-d') . '.xlsx'
        );
    }

    private function priceEagerLoads(): array
    {
        return [
            'item:id,code,short_name,description,item_unit_id,cost_calculation',
            'item.itemUnit:id,name,short_name',
            'item.priceHistories' => fn($q) => $q
                ->whereIn('source_type', ['purchase_item', 'initial'])
                ->orderBy('id', 'desc')
                ->with(['purchaseItemSource.purchase.currency']),
        ];
    }

    private function transformCurrentPriceRow(ItemPrice $row, ?\Illuminate\Support\Collection $auditIndex = null): array
    {
        $histories      = $row->item?->priceHistories ?? collect();
        $currentHistory = $histories->firstWhere('is_current', true);
        $auditRow       = $auditIndex?->get($row->item_id);

        return [
            'item_id'          => $row->item_id,
            'calculation_type' => $currentHistory?->calculation_type ?? $row->item?->cost_calculation,
            'item_code'        => $row->item?->code,
            'item_name'        => $row->item?->description,
            'unit'             => $row->item?->itemUnit?->only(['id', 'name', 'short_name']),
            'price_usd'        => $row->price_usd,
            'effective_date'   => $row->effective_date,
            'history'          => $histories->take(5)->map(fn($h) => $this->transformRow($h))->values(),
            'price_check'      => $auditIndex !== null ? [
                'correct_price' => $auditRow['correct_price'] ?? null,
                'difference'    => $auditRow['difference'] ?? 0,
                'diff_percent'  => $auditRow['diff_percent'] ?? '0%',
                'needs_fix'     => $auditRow !== null,
            ] : null,
        ];
    }

    private function transformRow(ItemPriceHistory $history): array
    {
        $purchaseItem = $history->source_type === 'purchase_item' ? $history->purchaseItemSource : null;
        $purchase     = $purchaseItem?->purchase;
        $currency     = $purchase?->currency;

        return [
            'id'               => $history->id,
            'source_type'      => $history->source_type,
            'calculation_type' => $history->calculation_type,
            'is_current'       => $history->is_current,
            'effective_date'   => $history->effective_date,
            'source_date'      => $history->created_at,
            'source_id'        => $purchase?->id,
            'source_prefix'    => $purchase?->prefix,
            'source_code'      => $purchase ? $purchase->code : $history->source_type,
            'cost_price'       => $purchaseItem ? $purchaseItem->price : $history->price_usd,
            'price_usd'        => $purchaseItem ? $purchaseItem->cost_per_item_usd : $history->price_usd,
            'discount_percent' => $purchaseItem?->discount_percent ?? 0,
            'currency_rate'    => $purchase?->currency_rate ?? 1,
            'exp_share'        => $purchaseItem && $purchaseItem->quantity > 0
                                    ? round($purchaseItem->total_expense_usd / $purchaseItem->quantity, 4)
                                    : 0,
            'exp_share_total'  => $purchaseItem?->total_expense_usd ?? 0,
            'exp_pct'          => $purchaseItem && $purchaseItem->final_total_cost_usd > 0
                                    ? round(($purchaseItem->total_expense_usd / $purchaseItem->final_total_cost_usd) * 100, 2)
                                    : 0,
            'remark'           => $history->note,
            'currency'         => $currency?->only(['id', 'symbol', 'symbol_position', 'decimal_places', 'decimal_separator', 'thousand_separator']),
        ];
    }
}
