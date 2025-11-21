<?php

namespace App\Services\Inventory;

use App\Models\Inventory\ItemPrice;
use App\Models\Inventory\ItemPriceHistory;
use App\Models\Items\Item;
use App\Models\Suppliers\Purchase;
use App\Models\Suppliers\PurchaseItem;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Item Price Calculation Service
 *
 * Manages item pricing using Weighted Average Cost method. Price calculation happens
 * BEFORE inventory updates to ensure accurate calculations without backwards math.
 *
 * Key Features:
 * - Weighted average price calculation on each purchase
 * - Global pricing across all warehouses
 * - Immutable price history for audit trail
 * - Support for multiple cost calculation methods (Weighted Average, Last Cost)
 *
 * @see docs/INVENTORY_PRICING.md For complete documentation
 * @package App\Services\Inventory
 */
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
     * Update item price based on purchase return
     */
    public static function updateFromPurchaseReturn(\App\Models\Suppliers\PurchaseReturn $purchaseReturn, \App\Models\Suppliers\PurchaseReturnItem $purchaseReturnItem, bool $isUpdate = false, ?int $oldQuantity = null): void
    {
        $item = $purchaseReturnItem->item;
        $currentItemPrice = self::getCurrentPrice($purchaseReturnItem->item_id);

        if (!$currentItemPrice) {
            // No current price exists, nothing to update
            return;
        }

        // Only recalculate for weighted average cost items
        if ($item->cost_calculation !== Item::COST_WEIGHTED_AVERAGE) {
            return;
        }

        $oldPriceUsd = $currentItemPrice->price_usd;
        $newPriceUsd = self::calculateWeightedAveragePriceAfterReturn(
            $purchaseReturnItem,
            $oldPriceUsd,
            $isUpdate,
            $oldQuantity
        );

        $priceDifference = abs($newPriceUsd - $oldPriceUsd);

        if ($priceDifference > 0) {
            self::updatePrice(
                $purchaseReturnItem->item_id,
                $newPriceUsd,
                $purchaseReturn->date,
                $oldPriceUsd,
                "Purchase Return #{$purchaseReturn->id}",
                'purchase_return',
                $purchaseReturn->id
            );
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
     * Get current price for an item
     */
    public static function getCurrentPrice(int $itemId): ?ItemPrice
    {
        return ItemPrice::byItem($itemId)->first();
    }

    /**
     * Calculate weighted average price after purchase return
     */
    private static function calculateWeightedAveragePriceAfterReturn(\App\Models\Suppliers\PurchaseReturnItem $purchaseReturnItem, float $currentPriceUsd, bool $isUpdate = false, ?int $oldQuantity = null): float
    {
        $returnedQuantity = $purchaseReturnItem->quantity;
        $returnedPriceUsd = $purchaseReturnItem->cost_per_item_usd;

        // Get current inventory BEFORE this return is processed
        $currentInventory = InventoryService::getTotalQuantityAcrossWarehouses($purchaseReturnItem->item_id);

        if ($isUpdate && $oldQuantity !== null) {
            // For updates: adjust current inventory for the old quantity
            $currentInventory = $currentInventory + $oldQuantity;
        }

        // Calculate inventory after return
        $inventoryAfterReturn = $currentInventory - $returnedQuantity;

        if ($inventoryAfterReturn <= 0) {
            // All inventory returned, price becomes 0 or we keep current price
            return $currentPriceUsd;
        }

        // Calculate new weighted average by removing returned items value
        // Formula: ((current_qty × current_price) - (returned_qty × returned_price)) / (current_qty - returned_qty)
        $currentTotalValue = $currentInventory * $currentPriceUsd;
        $returnedTotalValue = $returnedQuantity * $returnedPriceUsd;
        $newTotalValue = $currentTotalValue - $returnedTotalValue;

        // Ensure we don't get negative value
        if ($newTotalValue < 0) {
            $newTotalValue = 0;
        }

        return $inventoryAfterReturn > 0 ? ($newTotalValue / $inventoryAfterReturn) : $currentPriceUsd;
    }

    /**
     * Calculate weighted average price for an item
     */
    private static function calculateWeightedAveragePrice(PurchaseItem $purchaseItem, float $currentPriceUsd, bool $isUpdate = false, ?float $oldCostPerItemUsd = null, ?int $oldQuantity = null): float
    {
        $newQuantity = $purchaseItem->quantity;
        $newPriceUsd = $purchaseItem->cost_per_item_usd;

        if ($isUpdate && $oldCostPerItemUsd !== null && $oldQuantity !== null) {
            // For updates: Recalculate from scratch using actual PurchaseItem records
            // This approach automatically fixes any previously corrupted prices

            // Get current inventory (BEFORE this purchase update is applied to inventory)
            // Note: Current inventory includes the OLD quantity from this purchase
            $currentInventory = InventoryService::getTotalQuantityAcrossWarehouses($purchaseItem->item_id);

            return self::recalculateFromPurchaseHistory(
                $purchaseItem->item_id,
                $purchaseItem->id, // Exclude this purchase item
                $oldQuantity,      // Old quantity (currently in inventory)
                $newQuantity,
                $newPriceUsd,
                $currentInventory
            );
        }

        // For new purchases: Get current inventory BEFORE this purchase
        // (Price calculation now happens BEFORE inventory update, so this is the actual current inventory)
        $currentQuantity = InventoryService::getTotalQuantityAcrossWarehouses($purchaseItem->item_id);

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
    private static function recalculateFromPurchaseHistory(
        int $itemId,
        int $excludePurchaseItemId,
        int $oldQuantity,
        int $newQuantity,
        float $newPriceUsd,
        int $currentInventory
    ): float {
        // Get all OTHER purchase items for this item across all warehouses
        $otherPurchases = \App\Models\Suppliers\PurchaseItem::where('item_id', $itemId)
            ->where('id', '!=', $excludePurchaseItemId)
            ->get(['quantity', 'cost_per_item_usd']);

        // Calculate total value and quantity from other purchases
        $totalValueFromOthers = 0;
        $totalQtyFromOthers = 0;

        foreach ($otherPurchases as $purchase) {
            $totalValueFromOthers += ($purchase->quantity * $purchase->cost_per_item_usd);
            $totalQtyFromOthers += $purchase->quantity;
        }

        // Calculate inventory WITHOUT the purchase being updated
        // (Current inventory still includes the OLD quantity from this purchase)
        $inventoryWithoutThisPurchase = $currentInventory - $oldQuantity;

        if ($inventoryWithoutThisPurchase <= 0) {
            // No other inventory, just use the new price
            return $newPriceUsd;
        }

        // If we have other purchases but inventory is less than total purchased
        // (meaning some was sold), we need to calculate the average of what remains
        if ($inventoryWithoutThisPurchase < $totalQtyFromOthers) {
            // Some inventory was sold, so we calculate average cost of remaining inventory
            // Use the weighted average of all other purchases as the base cost
            $averageCostOfOthers = $totalQtyFromOthers > 0
                ? ($totalValueFromOthers / $totalQtyFromOthers)
                : 0;

            $baseValue = $inventoryWithoutThisPurchase * $averageCostOfOthers;
        } else {
            // All purchased inventory is still in stock
            $baseValue = $totalValueFromOthers;
        }

        // Add the new purchase
        $totalValue = $baseValue + ($newQuantity * $newPriceUsd);
        $totalQuantity = $inventoryWithoutThisPurchase + $newQuantity;

        return $totalQuantity > 0 ? ($totalValue / $totalQuantity) : $newPriceUsd;
    }

    /**
     * Create new item price record
     */
    private static function createPrice(int $itemId, float $priceUsd, string $effectiveDate, ?string $reason = null, ?string $sourceType = null, ?int $sourceId = null): ItemPrice
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
                'average_weighted_price' => $priceUsd,
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
    private static function updatePrice(int $itemId, float $newPriceUsd, string $effectiveDate, ?float $oldPriceUsd = null, ?string $reason = null, ?string $sourceType = null, ?int $sourceId = null): ItemPrice
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
                'average_weighted_price' => $newPriceUsd,
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

}