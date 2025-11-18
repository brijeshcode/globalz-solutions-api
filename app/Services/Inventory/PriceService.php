<?php

namespace App\Services\Inventory;

use App\Models\Inventory\ItemPrice;
use App\Models\Inventory\ItemPriceHistory;
use App\Models\Items\Item;
use App\Models\Suppliers\Purchase;
use App\Models\Suppliers\PurchaseItem;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class PriceService
{
    /**
     * Update item price based on purchase
     */
    public static function updateFromPurchase(Purchase $purchase, PurchaseItem $purchaseItem, bool $isUpdate = false, ?float $oldCostPerItemUsd = null, ?int $oldQuantity = null): void
    {
        $item = $purchaseItem->item;
        $newPriceUsd = $purchaseItem->cost_per_item_usd;

        $currentItemPrice = self::getCurrentPrice($purchaseItem->item_id);

        if ($currentItemPrice) {
            $oldPriceUsd = $currentItemPrice->price_usd;
            // Calculate price based on item's cost calculation method
            if ($item->cost_calculation === Item::COST_WEIGHTED_AVERAGE) {
                $newPriceUsd = self::calculateWeightedAveragePrice($purchaseItem, $oldPriceUsd, $isUpdate, $oldCostPerItemUsd, $oldQuantity);
            }
            // For COST_LAST_COST, use the new price as-is

            $priceDifference = abs($newPriceUsd - $oldPriceUsd);

            if ($priceDifference > 0) {
                self::updatePrice($purchaseItem->item_id, $newPriceUsd, $purchase->date, $oldPriceUsd, "Purchase #{$purchase->id}", 'purchase', $purchase->id);
            }
        } else {
            self::createPrice($purchaseItem->item_id, $newPriceUsd, $purchase->date, "Purchase #{$purchase->id}", 'purchase', $purchase->id);
        }
    }

    /**
     * Initialize price when creating a new item
     */
    public static function initializeFromItem(Item $item): void
    {
        if ($item->starting_price > 0) {
            self::createPrice(
                $item->id, 
                $item->starting_price, 
                $item->created_at->format('Y-m-d'), 
                'Initial price from item creation',
                'initial', 
                $item->id
            );
        }
    }

    /**
     * Update item price from stock adjustment
     */
    public static function updateFromAdjustment(int $itemId, float $newPriceUsd, string $effectiveDate, ?string $reason = null): void
    {
        $currentItemPrice = self::getCurrentPrice($itemId);
        
        if ($currentItemPrice) {
            $oldPriceUsd = $currentItemPrice->price_usd;
            $priceDifference = abs($newPriceUsd - $oldPriceUsd);
            
            if ($priceDifference > 0.01) {
                self::updatePrice($itemId, $newPriceUsd, $effectiveDate, $oldPriceUsd, $reason, 'adjustment');
            }
        } else {
            self::createPrice($itemId, $newPriceUsd, $effectiveDate, $reason, 'adjustment');
        }
    }

    /**
     * Update price when stock is physically adjusted (stock reconciliation)
     */
    public static function updateFromStockAdjustment(int $itemId, int $physicalQuantity, int $systemQuantity, ?float $adjustedPriceUsd = null, ?string $reason = null): void
    {
        $item = Item::findOrFail($itemId);
        $effectiveDate = now()->format('Y-m-d');
        $defaultReason = "Stock adjustment - Physical: {$physicalQuantity}, System: {$systemQuantity}";
        $finalReason = $reason ?? $defaultReason;

        // If adjusted price is provided, update it
        if ($adjustedPriceUsd !== null) {
            self::updateFromAdjustment($itemId, $adjustedPriceUsd, $effectiveDate, $finalReason);
            return;
        }

        // If no price provided but item uses weighted average, recalculate based on adjustment
        if ($item->cost_calculation === Item::COST_WEIGHTED_AVERAGE) {
            $currentPrice = self::getCurrentPrice($itemId);
            
            if ($currentPrice && $physicalQuantity > 0 && $systemQuantity > 0) {
                // Calculate price adjustment based on quantity difference
                $quantityDifference = $physicalQuantity - $systemQuantity;
                
                if ($quantityDifference != 0) {
                    // For weighted average, we might need to adjust the price based on the lost/found inventory
                    // This is complex and might require additional business logic
                    // For now, we'll just log the adjustment without changing price
                    
                    // Create history entry for stock adjustment (no price change)
                    ItemPriceHistory::create([
                        'item_id' => $itemId,
                        'price_usd' => $currentPrice->price_usd,
                        'average_waited_price' => $currentPrice->price_usd,
                        'latest_price' => $currentPrice->price_usd,
                        'effective_date' => $effectiveDate,
                        'source_type' => 'stock_adjustment',
                        'source_id' => null,
                        'note' => $finalReason . ' (no price change)',
                    ]);
                }
            }
        }
    }

    /**
     * Get current price for an item
     */
    public static function getCurrentPrice(int $itemId): ?ItemPrice
    {
        return ItemPrice::byItem($itemId)->first();
    }

    /**
     * Get price history for an item
     */
    public static function getPriceHistory(int $itemId, ?int $limit = 50): array
    {
        return ItemPriceHistory::where('item_id', $itemId)
            ->orderBy('effective_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get()
            ->toArray();
    }

    /**
     * Calculate weighted average price for an item
     */
    protected static function calculateWeightedAveragePrice(PurchaseItem $purchaseItem, float $currentPriceUsd, bool $isUpdate = false, ?float $oldCostPerItemUsd = null, ?int $oldQuantity = null): float
    {
        // Get current inventory quantity (this already includes the newly added purchase)
        $inventoryAfterPurchase = InventoryService::getQuantity(
            $purchaseItem->item_id,
            $purchaseItem->purchase->warehouse_id
        );

        $newQuantity = $purchaseItem->quantity;
        $newPriceUsd = $purchaseItem->cost_per_item_usd;

        if ($isUpdate && $oldCostPerItemUsd !== null && $oldQuantity !== null) {
            // For updates: Recalculate from scratch using actual PurchaseItem records
            // This approach automatically fixes any previously corrupted prices

            return self::recalculateFromPurchaseHistory(
                $purchaseItem->item_id,
                $purchaseItem->purchase->warehouse_id,
                $purchaseItem->id, // Exclude this purchase item
                $newQuantity,
                $newPriceUsd,
                $inventoryAfterPurchase
            );
        }

        // For new purchases: calculate inventory BEFORE this purchase was added
        // (Inventory is updated before price calculation, so we need to subtract the new quantity)
        $currentQuantity = $inventoryAfterPurchase - $newQuantity;

        if ($currentQuantity <= 0) {
            return $newPriceUsd;
        }

        // Weighted average formula:
        // ((current_qty * current_price) + (new_qty * new_price)) / (current_qty + new_qty)
        $totalValue = ($currentQuantity * $currentPriceUsd) + ($newQuantity * $newPriceUsd);
        $totalQuantity = $currentQuantity + $newQuantity;

        return $totalQuantity > 0 ? ($totalValue / $totalQuantity) : $newPriceUsd;
    }

    /**
     * Recalculate weighted average from actual purchase history
     * This is used for updates to ensure accuracy even if previous prices were corrupted
     */
    protected static function recalculateFromPurchaseHistory(
        int $itemId,
        int $warehouseId,
        int $excludePurchaseItemId,
        int $newQuantity,
        float $newPriceUsd,
        int $currentInventory
    ): float {
        // Get all OTHER purchase items for this item in this warehouse
        $otherPurchases = \App\Models\Suppliers\PurchaseItem::where('item_id', $itemId)
            ->where('id', '!=', $excludePurchaseItemId)
            ->whereHas('purchase', function ($query) use ($warehouseId) {
                $query->where('warehouse_id', $warehouseId);
            })
            ->get(['quantity', 'cost_per_item_usd']);

        // Calculate total value and quantity from other purchases
        $totalValueFromOthers = 0;
        $totalQtyFromOthers = 0;

        foreach ($otherPurchases as $purchase) {
            $totalValueFromOthers += ($purchase->quantity * $purchase->cost_per_item_usd);
            $totalQtyFromOthers += $purchase->quantity;
        }

        // Calculate how much inventory remains from other purchases
        // (Current inventory - new purchase quantity)
        $inventoryWithoutNewPurchase = $currentInventory - $newQuantity;

        if ($inventoryWithoutNewPurchase <= 0) {
            // No other inventory, just use the new price
            return $newPriceUsd;
        }

        // If we have other purchases but inventory is less than total purchased
        // (meaning some was sold), we need to calculate the average of what remains
        if ($inventoryWithoutNewPurchase < $totalQtyFromOthers) {
            // Some inventory was sold, so we calculate average cost of remaining inventory
            // Use the weighted average of all other purchases as the base cost
            $averageCostOfOthers = $totalQtyFromOthers > 0
                ? ($totalValueFromOthers / $totalQtyFromOthers)
                : 0;

            $baseValue = $inventoryWithoutNewPurchase * $averageCostOfOthers;
        } else {
            // All purchased inventory is still in stock
            $baseValue = $totalValueFromOthers;
        }

        // Add the new purchase
        $totalValue = $baseValue + ($newQuantity * $newPriceUsd);
        $totalQuantity = $inventoryWithoutNewPurchase + $newQuantity;

        return $totalQuantity > 0 ? ($totalValue / $totalQuantity) : $newPriceUsd;
    }

    /**
     * Create new item price record
     */
    protected static function createPrice(int $itemId, float $priceUsd, string $effectiveDate, ?string $reason = null, ?string $sourceType = null, ?int $sourceId = null): ItemPrice
    {
        return DB::transaction(function () use ($itemId, $priceUsd, $effectiveDate, $reason, $sourceType, $sourceId) {
            $itemPrice = ItemPrice::create([
                'item_id' => $itemId,
                'price_usd' => $priceUsd,
                'effective_date' => $effectiveDate,
            ]);

            // Create price history entry
            ItemPriceHistory::create([
                'item_id' => $itemId,
                'price_usd' => $priceUsd,
                'average_waited_price' => $priceUsd,
                'latest_price' => 0, // No previous price
                'effective_date' => $effectiveDate,
                'source_type' => $sourceType ?? 'initial',
                'source_id' => $sourceId,
                'note' => $reason ?? 'Initial price',
            ]);

            return $itemPrice;
        });
    }

    /**
     * Update existing item price
     */
    protected static function updatePrice(int $itemId, float $newPriceUsd, string $effectiveDate, ?float $oldPriceUsd = null, ?string $reason = null, ?string $sourceType = null, ?int $sourceId = null): ItemPrice
    {
        return DB::transaction(function () use ($itemId, $newPriceUsd, $effectiveDate, $oldPriceUsd, $reason, $sourceType, $sourceId) {
            $itemPrice = ItemPrice::byItem($itemId)->first();
            $oldPrice = $oldPriceUsd ?? $itemPrice->price_usd;

            // Update current item price
            $itemPrice->update([
                'price_usd' => $newPriceUsd,
                'effective_date' => $effectiveDate,
            ]);

            // Create price history entry for the change
            ItemPriceHistory::create([
                'item_id' => $itemId,
                'price_usd' => $newPriceUsd,
                'average_waited_price' => $newPriceUsd,
                'latest_price' => $oldPrice,
                'effective_date' => $effectiveDate,
                'source_type' => $sourceType ?? 'manual',
                'source_id' => $sourceId,
                'note' => $reason ?? 'Price update',
            ]);

            return $itemPrice;
        });
    }


    /**
     * Set item price manually (for adjustments)
     */
    public static function setPrice(int $itemId, float $priceUsd, string $effectiveDate, ?string $reason = null): ItemPrice
    {
        self::validateItem($itemId);

        $currentPrice = self::getCurrentPrice($itemId);

        if ($currentPrice) {
            return self::updatePrice($itemId, $priceUsd, $effectiveDate, $currentPrice->price_usd, $reason);
        } else {
            return self::createPrice($itemId, $priceUsd, $effectiveDate, $reason);
        }
    }

    /**
     * Bulk price updates
     */
    public static function bulkUpdatePrices(array $priceUpdates, string $effectiveDate, ?string $reason = null): array
    {
        return DB::transaction(function () use ($priceUpdates, $effectiveDate, $reason) {
            $results = [];

            foreach ($priceUpdates as $update) {
                $itemId = $update['item_id'];
                $priceUsd = $update['price_usd'];
                $itemReason = $update['reason'] ?? $reason;

                $results[] = self::setPrice($itemId, $priceUsd, $effectiveDate, $itemReason);
            }

            return $results;
        });
    }

    /**
     * Get price trend analysis for an item
     */
    public static function getPriceTrend(int $itemId, int $days = 30): array
    {
        $startDate = now()->subDays($days)->format('Y-m-d');

        $history = ItemPriceHistory::where('item_id', $itemId)
            ->where('effective_date', '>=', $startDate)
            ->orderBy('effective_date', 'asc')
            ->get(['effective_date', 'price_usd'])
            ->toArray();

        if (empty($history)) {
            return [
                'trend' => 'stable',
                'change_percent' => 0,
                'history' => []
            ];
        }

        $firstPrice = $history[0]['price_usd'];
        $lastPrice = end($history)['price_usd'];
        $changePercent = $firstPrice > 0 ? (($lastPrice - $firstPrice) / $firstPrice) * 100 : 0;

        $trend = 'stable';
        if ($changePercent > 5) {
            $trend = 'increasing';
        } elseif ($changePercent < -5) {
            $trend = 'decreasing';
        }

        return [
            'trend' => $trend,
            'change_percent' => round($changePercent, 2),
            'first_price' => $firstPrice,
            'last_price' => $lastPrice,
            'history' => $history
        ];
    }

    /**
     * Calculate price based on item's cost calculation method
     */
    public static function calculatePrice(Item $item, float $newCostUsd, int $newQuantity, ?int $warehouseId = null): float
    {
        if ($item->cost_calculation === Item::COST_LAST_COST) {
            return $newCostUsd;
        }

        if ($item->cost_calculation === Item::COST_WEIGHTED_AVERAGE) {
            $currentPrice = self::getCurrentPrice($item->id);
            
            if (!$currentPrice) {
                return $newCostUsd;
            }

            // Get total current inventory across all warehouses if no specific warehouse
            // $currentQuantity = $warehouseId 
            //     ? InventoryService::getQuantity($item->id, $warehouseId)
            //     : InventoryService::getTotalQuantityAcrossWarehouses($item->id);

            $currentQuantity = InventoryService::getTotalQuantityAcrossWarehouses($item->id);

            if ($currentQuantity <= 0) {
                return $newCostUsd;
            }

            $totalValue = ($currentQuantity * $currentPrice->price_usd) + ($newQuantity * $newCostUsd);
            $totalQuantity = $currentQuantity + $newQuantity;

            return $totalQuantity > 0 ? ($totalValue / $totalQuantity) : $newCostUsd;
        }

        return $newCostUsd;
    }

    /**
     * Check if item can have its starting price changed
     */
    public static function canUpdateStartingPrice(int $itemId): bool
    {
        // Check if any purchases exist
        $hasPurchases = DB::table('purchase_items')->where('item_id', $itemId)->exists();
        
        // Check if any price history exists (indicating transactions)
        $hasPriceHistory = ItemPriceHistory::where('item_id', $itemId)->exists();
        
        // Check if any inventory adjustments exist (future stock adjustment module)
        // $hasAdjustments = DB::table('inventory_adjustments')->where('item_id', $itemId)->exists();
        
        return !$hasPurchases && !$hasPriceHistory; // && !$hasAdjustments
    }

    /**
     * Get starting price change impact details
     */
    public static function getStartingPriceChangeImpact(int $itemId): array
    {
        $purchaseCount = DB::table('purchase_items')->where('item_id', $itemId)->count();
        $priceHistoryCount = ItemPriceHistory::where('item_id', $itemId)->count();
        
        $totalTransactions = $purchaseCount + $priceHistoryCount;
        
        return [
            'can_change' => $totalTransactions === 0,
            'purchase_count' => $purchaseCount,
            'price_history_count' => $priceHistoryCount,
            'total_transactions' => $totalTransactions,
            'warning_message' => $totalTransactions > 0 
                ? "Cannot change starting price. This item has {$totalTransactions} transaction(s) that would be affected."
                : null
        ];
    }

    /**
     * Validate item exists
     */
    protected static function validateItem(int $itemId): void
    {
        if (!Item::find($itemId)) {
            throw new InvalidArgumentException("Item with ID {$itemId} not found");
        }
    }
}