<?php

namespace App\Http\Controllers\Api\Items;

use App\Exports\ItemCurrentPricesExport;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Inventory\ItemPrice;
use App\Models\Inventory\ItemPriceHistory;
use App\Models\Suppliers\PurchaseExpense;
use App\Services\Inventory\ItemCostLedger;
use App\Services\Inventory\PriceService;
use App\Traits\HasPagination;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ItemCostHistoryController extends Controller
{
    use HasPagination;

    public function index(Request $request): JsonResponse
    {

        $rows = ItemPriceHistory::where('item_id', $request->get('item_id'))
            ->whereIn('source_type', ['purchase_item', 'initial', 'calculation_type_change'])
            ->orderBy('id', 'desc')
            ->with(['purchaseItemSource.purchase.currency'])
            ->get();

        $expPctMap = $this->buildExpensePctMap($rows);

        return ApiResponse::index('Item cost history retrieved successfully',
            $rows->map(fn($row) => $this->transformRow($row, $expPctMap))->toArray()
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

        // When ?verify=1, diagnose each item ON THIS PAGE by replaying its stock
        // movements (ItemCostLedger) and attach a price_check explaining the first
        // thing that is wrong. Scoped to the page so it never has to "check all
        // items" in one shot — paging through verifies the whole catalogue.
        $auditIndex = null;
        if ($request->boolean('verify')) {
            $tolerance  = (float) $request->input('tolerance', 0.0);
            $auditIndex = $rows->getCollection()
                ->map(fn($row) => ItemCostLedger::diagnose($row->item_id, $tolerance))
                ->keyBy('item_id');
        }

        $allHistories = $rows->getCollection()->flatMap(
            fn($row) => $row->item?->priceHistories?->take(5) ?? collect()
        );
        $expPctMap = $this->buildExpensePctMap($allHistories);

        return ApiResponse::paginated('Current item prices retrieved successfully',
            $rows->through(fn($row) => $this->transformCurrentPriceRow($row, $auditIndex, $expPctMap))
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

        $allHistories = $rows->flatMap(fn($row) => $row->item?->priceHistories?->take(5) ?? collect());
        $expPctMap    = $this->buildExpensePctMap($allHistories);

        return Excel::download(
            new ItemCurrentPricesExport($rows->map(fn($row) => $this->transformCurrentPriceRow($row, null, $expPctMap))),
            'items-cost-list-' . now()->format('Y-m-d') . '.xlsx'
        );
    }

    private function priceEagerLoads(): array
    {
        return [
            'item:id,code,short_name,description,item_unit_id,cost_calculation',
            'item.itemUnit:id,name,short_name',
            'item.priceHistories' => fn($q) => $q
                ->whereIn('source_type', ['purchase_item', 'initial', 'calculation_type_change'])
                ->orderBy('id', 'desc')
                ->with(['purchaseItemSource.purchase.currency']),
        ];
    }

    private function transformCurrentPriceRow(ItemPrice $row, ?\Illuminate\Support\Collection $auditIndex = null, array $expPctMap = []): array
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
            'history'          => $histories->take(5)->map(fn($h) => $this->transformRow($h, $expPctMap))->values(),
            'price_check'      => $auditIndex !== null ? [
                'status'         => $auditRow['status'] ?? null,            // ok | stale_price | wrong_history | missing_history | missing_item_price | no_movements
                'needs_fix'      => isset($auditRow['status']) && !in_array($auditRow['status'], ['ok', 'no_movements'], true),
                'correct_price'  => $auditRow['correct_price'] ?? null,
                'current_price'  => $auditRow['current_price'] ?? null,
                'difference'     => $auditRow['difference'] ?? 0,
                'diff_percent'   => $auditRow['diff_percent'] ?? '0%',
                // "why": populated for history-level failures so you can see where it broke
                'at_purchase'      => $auditRow['at_purchase'] ?? null,
                'at_date'          => $auditRow['at_date'] ?? null,
                'stored_average'   => $auditRow['stored_average'] ?? null,
                'expected_average' => $auditRow['expected_average'] ?? null,
            ] : null,
        ];
    }

    private function buildExpensePctMap(Collection $histories): array
    {
        $purchaseData = [];
        foreach ($histories as $history) {
            $purchase = $history->purchaseItemSource?->purchase;
            if (!$purchase) continue;
            $purchaseData[$purchase->id] = (float) $purchase->total_usd;
        }

        if (empty($purchaseData)) return [];

        $distributable = PurchaseExpense::join('expense_transactions', 'purchase_expenses.expense_transaction_id', '=', 'expense_transactions.id')
            ->whereNull('expense_transactions.deleted_at')
            ->where('purchase_expenses.exclude_from_item_cost', false)
            ->whereIn('purchase_expenses.purchase_id', array_keys($purchaseData))
            ->groupBy('purchase_expenses.purchase_id')
            ->selectRaw('purchase_expenses.purchase_id, SUM(expense_transactions.amount_usd) as distributable_usd')
            ->pluck('distributable_usd', 'purchase_id');

        $map = [];
        foreach ($purchaseData as $purchaseId => $totalUsd) {
            $distributableUsd    = (float) ($distributable[$purchaseId] ?? 0);
            $map[$purchaseId] = $totalUsd > 0 ? round($distributableUsd / $totalUsd * 100, 2) : 0;
        }

        return $map;
    }

    private function transformRow(ItemPriceHistory $history, array $expPctMap = []): array
    {
        $inputs = $history->calculation_inputs;

        // Fallback to live relationship for records created before calculation_inputs was added
        if (!$inputs && $history->source_type === 'purchase_item') {
            $purchaseItem = $history->purchaseItemSource;
            $purchase     = $purchaseItem?->purchase;
            $currency     = $purchase?->currency;

            $qty             = $purchaseItem?->quantity ?? 0;
            $totalExpenseUsd = $purchaseItem?->total_expense_usd ?? 0;

            $inputs = [
                'quantity'                => $qty,
                'discount_percent'        => $purchaseItem?->discount_percent ?? 0,
                'cost_price'              => $purchaseItem?->price,
                'final_cost_per_item_usd' => $purchaseItem?->cost_per_item_usd,
                'expense_per_item_usd'    => $qty > 0 ? $totalExpenseUsd / $qty : 0,
                'total_expense_usd'       => $totalExpenseUsd,
                'final_total_cost_usd'    => $purchaseItem?->final_total_cost_usd,
                'currency_rate'           => $purchase?->currency_rate ?? 1,
                'purchase_id'             => $purchase?->id,
                'purchase_prefix'         => $purchase?->prefix,
                'purchase_code'           => $purchase?->code,
                'currency'                => $currency?->only(['id', 'symbol', 'symbol_position', 'decimal_places', 'decimal_separator', 'thousand_separator']),
            ];
        }

        $totalExpense = $inputs['total_expense_usd'] ?? 0;
        $purchaseId   = $history->purchaseItemSource?->purchase?->id;
        $expPct       = isset($inputs['purchase_exp_pct'])
            ? (float) $inputs['purchase_exp_pct']
            : ($purchaseId ? ($expPctMap[$purchaseId] ?? 0) : 0);

        return [
            'id'               => $history->id,
            'source_type'      => $history->source_type,
            'calculation_type' => $history->calculation_type,
            'is_current'       => $history->is_current,
            'effective_date'   => $history->effective_date,
            'source_date'      => $history->created_at,
            'source_id'        => $inputs['purchase_id'] ?? null,
            'source_prefix'    => $inputs['purchase_prefix'] ?? null,
            'source_code'      => $inputs['purchase_code'] ?? $history->source_type,
            'cost_price'       => $inputs['cost_price'] ?? $history->price_usd,
            'price_usd'        => $inputs['final_cost_per_item_usd'] ?? $history->price_usd,
            'discount_percent' => $inputs['discount_percent'] ?? 0,
            'currency_rate'    => $inputs['currency_rate'] ?? 1,
            'exp_share'        => round($inputs['expense_per_item_usd'] ?? 0, 4),
            'exp_share_total'  => $totalExpense,
            'purchase_exp_pct'          => $expPct,
            'remark'           => $history->note,
            'currency'         => $inputs['currency'] ?? null,
        ];
    }
}
