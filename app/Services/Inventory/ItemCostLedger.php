<?php

namespace App\Services\Inventory;

use App\Models\Inventory\ItemPrice;
use App\Models\Inventory\ItemPriceHistory;
use App\Models\Items\Item;
use App\Models\Items\ItemMovement;
use Illuminate\Support\Facades\DB;

/**
 * THE single source of truth for an item's cost price.
 *
 * Replays every stock movement for an item — opening balance, delivered
 * purchases, sales, returns, transfers and adjustments — in chronological
 * order and maintains a GLOBAL moving-average cost.
 *
 * Option A semantics: the cost_calculation method is a *pricing lens*, not a
 * different cost flow. The running weighted average is always maintained over
 * every purchase; "last_cost" simply publishes the most recent purchase cost
 * instead of the average. Because the average is never discarded, switching
 * average <-> last_cost loses no information and is fully reversible.
 *
 * The average only moves on cost-bearing IN events (opening + purchases).
 * Every other movement (sale, return, transfer, adjustment) changes quantity
 * at the current average, so the average itself is unchanged.
 *
 * ponytail: returns/transfers-in/add-adjustments re-enter stock at the current
 * average (no explicit per-document cost). Upgrade path: if adjustments/returns
 * ever carry their own cost, give them a cost-bearing branch like purchases.
 */
class ItemCostLedger
{
    /**
     * Replay the item and return its correct current price plus a per-event trace.
     *
     * @return array{
     *     price: float,
     *     average: float,
     *     last_cost: float,
     *     quantity: float,
     *     method: string,
     *     last_purchase_id: int|null,
     *     last_purchase_date: string|null,
     *     running_averages: array<int, float>,
     *     events: list<array<string, mixed>>
     * }|null  null when the item has no movements at all
     */
    public static function replay(int $itemId): ?array
    {
        $item = Item::find($itemId);
        if (!$item) {
            return null;
        }

        // Pull the whole movement stream; we re-order it in PHP below because
        // purchases must be sequenced by DELIVERY date (when stock physically
        // arrived), not the purchase document date — two purchases can arrive in
        // the opposite order they were dated.
        $movements = ItemMovement::byItem($itemId)->get();

        if ($movements->isEmpty()) {
            return null;
        }

        // Cost + delivery date for each purchase line, keyed by purchase_item id
        // (= movement id for purchase rows).
        $purchaseMeta = DB::table('purchase_items')
            ->join('purchases', 'purchase_items.purchase_id', '=', 'purchases.id')
            ->where('purchase_items.item_id', $itemId)
            ->get(['purchase_items.id', 'purchase_items.cost_per_item_usd', 'purchases.delivered_at', 'purchases.date'])
            ->keyBy('id');

        $startingPrice = (float) ($item->starting_price ?? 0);

        // Method timeline: the method stamped on each history row (purchases + calculation_type_change).
        $methodPoints = ItemPriceHistory::where('item_id', $itemId)
            ->whereNotNull('calculation_type')
            ->orderBy('effective_date')
            ->orderBy('id')
            ->get(['effective_date', 'calculation_type'])
            ->map(fn($r) => ['date' => (string) $r->effective_date, 'method' => $r->calculation_type])
            ->all();

        $methodAt = function (string $date) use ($methodPoints, $item): string {
            $active = $item->cost_calculation; // default when no earlier stamp exists
            foreach ($methodPoints as $p) {
                if ($p['date'] <= $date) {
                    $active = $p['method'];
                } else {
                    break;
                }
            }
            return $active;
        };

        $toDate = function ($value): string {
            if ($value instanceof \DateTimeInterface) {
                return $value->format('Y-m-d');
            }
            return $value ? substr((string) $value, 0, 10) : '';
        };

        // Normalise each movement to an effective date (delivery date for
        // purchases) then order: by date, stock-IN before stock-OUT on the same
        // date, then a stable tiebreak.
        $ordered = $movements->map(function ($m) use ($purchaseMeta, $toDate) {
            if ($m->transaction_type_key === 'purchase') {
                $meta = $purchaseMeta->get($m->id);
                $date = $toDate($meta->delivered_at ?? $meta->date ?? $m->transaction_date);
            } else {
                $date = $toDate($m->transaction_date);
            }

            return ['m' => $m, 'date' => $date, 'in' => (float) $m->credit > 0];
        })->sort(function ($a, $b) {
            return $a['date'] <=> $b['date']
                ?: ($b['in'] <=> $a['in'])                         // IN before OUT on the same date
                ?: ($a['m']->source_table <=> $b['m']->source_table)
                ?: ($a['m']->id <=> $b['m']->id);
        })->values();

        $qty = 0.0;
        $value = 0.0;
        $avg = 0.0;
        $lastCost = 0.0;
        $lastPurchaseId = null;
        $lastPurchaseDate = null;
        $runningAverages = [];
        $events = [];

        foreach ($ordered as $row) {
            $m = $row['m'];
            $date = $row['date'];
            $method = $methodAt($date);

            $credit = (float) $m->credit;
            $debit = (float) $m->debit;

            if ($m->transaction_type_key === 'purchase') {
                $cost = (float) ($purchaseMeta->get($m->id)->cost_per_item_usd ?? 0);
                $qty += $credit;
                $value += $credit * $cost;
                $avg = $qty > 0 ? $value / $qty : 0.0;
                $lastCost = $cost;
                $lastPurchaseId = (int) $m->parent_id;
                $lastPurchaseDate = $date;
                $runningAverages[(int) $m->id] = round($avg, 4);
            } elseif ($m->transaction_type_key === 'initial_inventory') {
                $qty += $credit;
                $value += $credit * $startingPrice;
                $avg = $qty > 0 ? $value / $qty : 0.0;
            } else {
                // Everything else moves quantity at the current average; the average is unchanged.
                $net = $credit - $debit;
                $qty += $net;
                $value += $net * $avg;
                if ($qty <= 0) {
                    $qty = 0.0;
                    $value = 0.0;
                }
            }

            $published = ($method === Item::COST_LAST_COST && $lastCost > 0) ? $lastCost : $avg;

            $events[] = [
                'type' => $m->transaction_type_key,
                'code' => $m->transaction_code,
                'date' => $date,
                'method' => $method,
                'quantity_after' => round($qty, 4),
                'average_after' => round($avg, 4),
                'last_cost' => round($lastCost, 4),
                'published_price' => round($published, 4),
                'source_id' => (int) $m->id,
            ];
        }

        // The item's live cost_calculation is the authority for the price published
        // NOW — this is also what makes a just-changed method recalc to the new
        // method immediately, before its change-row has even been written.
        $finalMethod = $item->cost_calculation;
        $price = ($finalMethod === Item::COST_LAST_COST && $lastCost > 0) ? $lastCost : $avg;

        return [
            'price' => round($price, 4),
            'average' => round($avg, 4),
            'last_cost' => round($lastCost, 4),
            'quantity' => round($qty, 4),
            'method' => $finalMethod,
            'last_purchase_id' => $lastPurchaseId,
            'last_purchase_date' => $lastPurchaseDate,
            'running_averages' => $runningAverages,
            'events' => $events,
        ];
    }

    /**
     * Compare the stored price data against a fresh replay and explain the FIRST
     * thing that is wrong — which is what makes the audit useful ("why is it failing").
     *
     * Returns status 'ok' when stored data matches the replay, otherwise a reason
     * code plus the divergent values. Statuses:
     *   no_movements       - item has no stock movements to price from
     *   missing_item_price - no item_prices row exists
     *   missing_history    - a delivered purchase has no price-history row
     *   wrong_history      - a purchase's stored average diverges from the replay (first offending purchase)
     *   stale_price        - item_prices price differs from the correct replayed price
     *   ok                 - everything matches
     *
     * @return array<string, mixed>
     */
    public static function diagnose(int $itemId, float $tolerance = 0.0): array
    {
        $replay = self::replay($itemId);
        if ($replay === null) {
            return ['item_id' => $itemId, 'status' => 'no_movements'];
        }

        $correct = $replay['price'];

        // 1) Per-purchase history check: find the first purchase whose stored average is wrong or missing.
        $historyByPurchaseItem = ItemPriceHistory::where('item_id', $itemId)
            ->where('source_type', 'purchase_item')
            ->get()
            ->keyBy('source_id');

        foreach ($replay['events'] as $event) {
            if ($event['type'] !== 'purchase') {
                continue;
            }

            $stored = $historyByPurchaseItem->get($event['source_id']);

            if (!$stored) {
                return array_merge(self::baseRow($itemId, $correct), [
                    'status' => 'missing_history',
                    'at_purchase' => $event['code'],
                    'at_date' => $event['date'],
                    'expected_average' => $event['average_after'],
                ]);
            }

            if (abs((float) $stored->average_weighted_price - $event['average_after']) > 0.000001) {
                return array_merge(self::baseRow($itemId, $correct), [
                    'status' => 'wrong_history',
                    'at_purchase' => $event['code'],
                    'at_date' => $event['date'],
                    'stored_average' => (float) $stored->average_weighted_price,
                    'expected_average' => $event['average_after'],
                ]);
            }
        }

        // 2) Current price check.
        $itemPrice = ItemPrice::where('item_id', $itemId)->first();
        if (!$itemPrice) {
            return array_merge(self::baseRow($itemId, $correct), ['status' => 'missing_item_price']);
        }

        $current = (float) $itemPrice->price_usd;
        $diff = abs($correct - $current);
        $diffPct = $current > 0 ? $diff / $current * 100 : null;

        if ($diff > 0.000001 && !($diffPct !== null && $diffPct <= $tolerance)) {
            return array_merge(self::baseRow($itemId, $correct), [
                'status' => 'stale_price',
                'current_price' => $current,
                'difference' => round($diff, 6),
                'diff_percent' => $diffPct !== null ? round($diffPct, 2) . '%' : 'N/A',
            ]);
        }

        return array_merge(self::baseRow($itemId, $correct), [
            'status' => 'ok',
            'current_price' => $current,
        ]);
    }

    /** @return array<string, mixed> */
    private static function baseRow(int $itemId, float $correctPrice): array
    {
        return [
            'item_id' => $itemId,
            'correct_price' => round($correctPrice, 6),
        ];
    }
}
