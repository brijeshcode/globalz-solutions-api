<?php

namespace App\Services\Customers;

use App\Models\Customers\SaleItems;
use App\Models\Inventory\ItemPriceHistory;
use App\Models\Items\Item;
use App\Models\Suppliers\Purchase;
use App\Models\Suppliers\PurchaseItem;
use Illuminate\Support\Facades\DB;

class SaleProfitRecalculationService
{
    /**
     * Recalculate profit for all sales of this purchase's items from its delivery
     * date to today. See buildChanges() for the cost-resolution rule; each updated
     * sale item is stamped with the history row id its cost came from.
     */
    public function recalculateForPurchase(Purchase $purchase): array
    {
        if ($purchase->status !== 'Delivered') {
            return ['skipped' => true, 'reason' => 'Purchase not delivered'];
        }

        $changes      = $this->buildChangesForPurchase($purchase);
        $updatedSales = $this->applyChanges($changes);

        return [
            'purchase_id'        => $purchase->id,
            'updated_sale_items' => count($changes),
            'updated_sales'      => $updatedSales,
        ];
    }

    /**
     * Apply precomputed changes (from buildChangesForPurchase) as bulk CASE updates —
     * one query per chunk instead of two queries per sale item. Raw updates fire no
     * model events (quiet by nature). Returns the number of updated sales.
     */
    public function applyChanges(array $changes): int
    {
        foreach (array_chunk($changes, 500) as $chunk) {
            $when = implode(' ', array_fill(0, count($chunk), 'WHEN ? THEN ?'));
            $in   = implode(',', array_fill(0, count($chunk), '?'));

            $ids = $costBindings = $historyBindings = $unitBindings = $totalBindings = [];
            foreach ($chunk as $c) {
                $ids[] = $c['sale_item_id'];
                array_push($costBindings, $c['sale_item_id'], $c['new_cost']);
                // ?? null keeps payloads from tasks created before cost_history_id existed working
                array_push($historyBindings, $c['sale_item_id'], $c['cost_history_id'] ?? null);
                array_push($unitBindings, $c['sale_item_id'], $c['new_unit_profit']);
                array_push($totalBindings, $c['sale_item_id'], $c['new_total_profit']);
            }

            DB::update(
                "UPDATE sale_items
                 SET cost_price      = CASE id {$when} END,
                     cost_history_id = CASE id {$when} END,
                     unit_profit     = CASE id {$when} END,
                     total_profit    = CASE id {$when} END,
                     updated_at      = ?
                 WHERE id IN ({$in}) AND deleted_at IS NULL",
                [...$costBindings, ...$historyBindings, ...$unitBindings, ...$totalBindings, now(), ...$ids]
            );
        }

        $affectedSaleIds = array_values(array_unique(array_column($changes, 'sale_id')));

        return $this->rollUpProfitToSales($affectedSaleIds);
    }

    /**
     * Recalculate profits for all delivered purchases.
     * Used by the weekly scheduler — one walk over everything, starting
     * from the oldest delivered purchase, acts as the weekly self-heal.
     */
    public function recalculateForAllDeliveredPurchases(): array
    {
        $oldest = Purchase::where('status', 'Delivered')
            ->orderByRaw('COALESCE(delivered_at, date) ASC')
            ->orderBy('id', 'asc')
            ->first();

        if (!$oldest) {
            return ['purchases_processed' => 0, 'updated_sale_items' => 0, 'updated_sales' => 0, 'errors' => []];
        }

        $itemIds = PurchaseItem::join('purchases', 'purchase_items.purchase_id', '=', 'purchases.id')
            ->where('purchases.status', 'Delivered')
            ->whereNull('purchase_items.deleted_at')
            ->whereNull('purchases.deleted_at')
            ->distinct()
            ->pluck('purchase_items.item_id');

        $fromDate = ($oldest->delivered_at ?? $oldest->date)->toDateString();

        $changes      = $this->buildChanges($itemIds->all(), $fromDate);
        $updatedSales = $this->applyChanges($changes);

        return [
            'purchases_processed' => Purchase::where('status', 'Delivered')->count(),
            'updated_sale_items'  => count($changes),
            'updated_sales'       => $updatedSales,
            'errors'              => [],
        ];
    }

    /**
     * Compute the list of sale item changes for a purchase without writing anything.
     * Used by recalculateForPurchase() and the preview endpoint on PurchasesController.
     */
    public function buildChangesForPurchase(Purchase $purchase): array
    {
        if ($purchase->status !== 'Delivered') {
            return [];
        }

        $itemIds = $purchase->purchaseItems()->pluck('item_id')->unique()->values();

        if ($itemIds->isEmpty()) {
            return [];
        }

        $fromDate = ($purchase->delivered_at ?? $purchase->date)->toDateString();

        return $this->buildChanges($itemIds->all(), $fromDate);
    }

    /**
     * The walk — pointer-first:
     *
     *  1. A sale stamped with a purchase-sourced cost_history_id is permanently bound
     *     to that purchase item: its correct cost is that purchase item's NEWEST
     *     history row (reflecting expense corrections whenever they were entered).
     *     No sale-date matching involved — the purchase is the final call.
     *  2. A sale with no stamp (legacy rows) is assigned once by window — the last
     *     purchase of the item delivered on or before the sale date — and gets that
     *     purchase item's newest row AND the stamp, so rule 1 applies from then on.
     *  3. A sale stamped with a non-purchase row (manual price, initial price) is
     *     left untouched — that cost was entered deliberately.
     *
     * Sales already carrying the winning row id (and matching cost) are skipped,
     * making repeated runs near-free.
     */
    private function buildChanges(array $itemIds, string $fromDate): array
    {
        if (empty($itemIds)) {
            return [];
        }

        $costTypeByItem = Item::whereIn('id', $itemIds)->pluck('cost_calculation', 'id');

        // All delivered purchase items of these items, any date — stamps may point
        // to purchases delivered before $fromDate.
        $purchaseItems = PurchaseItem::join('purchases', 'purchase_items.purchase_id', '=', 'purchases.id')
            ->where('purchases.status', 'Delivered')
            ->whereIn('purchase_items.item_id', $itemIds)
            ->whereNull('purchase_items.deleted_at')
            ->whereNull('purchases.deleted_at')
            ->selectRaw('purchase_items.id, purchase_items.item_id, DATE(COALESCE(purchases.delivered_at, purchases.date)) as delivered_date')
            ->get();

        if ($purchaseItems->isEmpty()) {
            return [];
        }

        // Newest (settled) history row per purchase item
        $latestRowByPurchaseItem = ItemPriceHistory::where('source_type', 'purchase_item')
            ->whereIn('source_id', $purchaseItems->pluck('id'))
            ->orderBy('id', 'desc')
            ->get(['id', 'source_id', 'price_usd', 'average_weighted_price'])
            ->unique('source_id')
            ->keyBy('source_id');

        // Window fallback for unstamped sales: purchase items per item, oldest delivery
        // first; same-day deliveries settle by id so the later purchase wins the tie.
        $windowsByItem = [];
        foreach ($purchaseItems as $purchaseItem) {
            $windowsByItem[$purchaseItem->item_id][] = [
                'delivered_date'   => $purchaseItem->delivered_date,
                'purchase_item_id' => $purchaseItem->id,
            ];
        }
        foreach ($windowsByItem as &$windows) {
            usort($windows, fn ($a, $b) => strcmp($a['delivered_date'], $b['delivered_date']) ?: $a['purchase_item_id'] <=> $b['purchase_item_id']);
        }
        unset($windows);

        // All affected sales in one query — only the columns the walk needs
        $saleItems = SaleItems::join('sales', 'sale_items.sale_id', '=', 'sales.id')
            ->whereIn('sale_items.item_id', $itemIds)
            ->whereDate('sales.date', '>=', $fromDate)
            ->whereNull('sale_items.deleted_at')
            ->whereNull('sales.deleted_at')
            ->get([
                'sale_items.id', 'sale_items.sale_id', 'sale_items.item_id',
                'sale_items.quantity', 'sale_items.cost_price', 'sale_items.cost_history_id',
                'sale_items.unit_profit', 'sale_items.total_profit', 'sale_items.net_sell_price_usd',
                DB::raw('sales.date as sale_date'),
            ]);

        // Resolve stamped rows in one query — withTrashed because a stamp may point to
        // a row soft-deleted when its purchase item was removed (handled as unstamped).
        $stampedRows = ItemPriceHistory::withTrashed()
            ->whereIn('id', $saleItems->pluck('cost_history_id')->filter()->unique())
            ->get(['id', 'source_type', 'source_id'])
            ->keyBy('id');

        $changes = [];

        foreach ($saleItems as $saleItem) {
            $stamp = $saleItem->cost_history_id ? $stampedRows->get($saleItem->cost_history_id) : null;

            // Rule 3: manual/initial price — deliberately entered, leave untouched
            if ($stamp && $stamp->source_type !== 'purchase_item') {
                continue;
            }

            $targetPurchaseItemId = null;

            if ($stamp && $latestRowByPurchaseItem->has($stamp->source_id)) {
                // Rule 1: bound to its purchase item — refresh to that purchase's settled cost
                $targetPurchaseItemId = $stamp->source_id;
            } else {
                // Rule 2: no stamp (or its purchase is gone) — assign by sale-date window
                foreach ($windowsByItem[$saleItem->item_id] ?? [] as $window) {
                    if ($window['delivered_date'] > $saleItem->sale_date) {
                        break;
                    }
                    $targetPurchaseItemId = $window['purchase_item_id'];
                }
            }

            $latest = $targetPurchaseItemId ? $latestRowByPurchaseItem->get($targetPurchaseItemId) : null;

            if (!$latest) {
                continue;
            }

            $newCost = ($costTypeByItem[$saleItem->item_id] ?? null) === Item::COST_LAST_COST
                ? (float) $latest->price_usd
                : (float) $latest->average_weighted_price;

            $oldCost = (float) $saleItem->cost_price;

            // Already stamped with the winning row and matching cost — nothing to do
            if ((int) $saleItem->cost_history_id === $latest->id && abs($oldCost - $newCost) <= 0.000001) {
                continue;
            }

            $changes[] = [
                'sale_item_id'     => $saleItem->id,
                'sale_id'          => $saleItem->sale_id,
                'item_id'          => $saleItem->item_id,
                'sale_date'        => $saleItem->sale_date,
                'quantity'         => (float) $saleItem->quantity,
                'cost_history_id'  => $latest->id,
                'old_cost'         => $oldCost,
                'new_cost'         => $newCost,
                'old_unit_profit'  => (float) $saleItem->unit_profit,
                'new_unit_profit'  => (float) $saleItem->net_sell_price_usd - $newCost,
                'old_total_profit' => (float) $saleItem->total_profit,
                'new_total_profit' => ((float) $saleItem->net_sell_price_usd - $newCost) * (float) $saleItem->quantity,
            ];
        }

        return $changes;
    }

    public function rollUpProfitToSales(array $saleIds): int
    {
        $saleIds = array_values(array_unique($saleIds));

        if (empty($saleIds)) {
            return 0;
        }

        $count = 0;

        // Single UPDATE joining the summed item profits per chunk — no per-sale
        // queries, no model events. LEFT JOIN + COALESCE keeps sales with no
        // remaining items at 0 - discount, matching the old behavior.
        foreach (array_chunk($saleIds, 500) as $chunk) {
            $in = implode(',', array_fill(0, count($chunk), '?'));

            $count += DB::update(
                "UPDATE sales s
                 LEFT JOIN (
                     SELECT sale_id, SUM(total_profit) AS items_total
                     FROM sale_items
                     WHERE sale_id IN ({$in}) AND deleted_at IS NULL
                     GROUP BY sale_id
                 ) si ON si.sale_id = s.id
                 SET s.total_profit = COALESCE(si.items_total, 0) - s.discount_amount_usd,
                     s.updated_at   = ?
                 WHERE s.id IN ({$in}) AND s.deleted_at IS NULL",
                [...$chunk, now(), ...$chunk]
            );
        }

        return $count;
    }
}
