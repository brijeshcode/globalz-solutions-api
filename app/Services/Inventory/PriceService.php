<?php

namespace App\Services\Inventory;

use App\Models\Inventory\ItemPrice;
use App\Models\Inventory\ItemPriceHistory;
use App\Models\Items\Item;
use App\Models\Suppliers\Purchase;
use App\Models\Suppliers\PurchaseItem;
use App\Models\Suppliers\SupplierItemPrice;
use App\Services\Suppliers\SupplierItemPriceService;
use App\Helpers\FeatureHelper;
use Illuminate\Support\Facades\DB;

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
    public static function updateFromPurchase(Purchase $purchase, PurchaseItem $purchaseItem, bool $isUpdate = false, ?float $oldCostPerItemUsd = null, ?int $oldQuantity = null, ?string $customNote = null): void
    {
        $item            = $purchaseItem->item;
        $newPriceUsd     = $purchaseItem->cost_per_item_usd;
        $calculationType = $item->cost_calculation;
        $effectiveDate   = $isUpdate ? now()->toDateString() : $purchase->date;

        $currentItemPrice = self::getCurrentPrice($purchaseItem->item_id);

        if ($currentItemPrice) {
            $oldPriceUsd = $currentItemPrice->price_usd;

            if ($item->cost_calculation === Item::COST_WEIGHTED_AVERAGE) {
                $newPriceUsd = self::calculateWeightedAveragePrice($purchaseItem, $oldPriceUsd, $isUpdate, $oldCostPerItemUsd, $oldQuantity);
            }

            $priceDifference = abs($newPriceUsd - $oldPriceUsd);

            if ($priceDifference > 0.000001) {
                $note      = $customNote ?? self::buildPurchaseUpdateNote($purchase, $purchaseItem, $isUpdate, $oldCostPerItemUsd, $oldQuantity);
                $isCurrent = self::shouldBeCurrentPrice($purchaseItem, $item->cost_calculation);

                self::updatePrice($purchaseItem->item_id, $newPriceUsd, $effectiveDate, $oldPriceUsd, $note, 'purchase_item', $purchaseItem->id, $calculationType, $isCurrent);
            }
        } else {
            $note = $customNote ?? "Initial price from Purchase #{$purchase->id}";
            self::createPrice($purchaseItem->item_id, $newPriceUsd, $effectiveDate, $note, 'purchase_item', $purchaseItem->id, $calculationType);
        }
    }

    /**
     * Recalculate and update the current item price from delivered purchase history.
     * Used when only quantity changes (no cost change) — updates item_prices directly
     * without creating a price history record.
     */
    public static function recalculateCurrentPrice(int $itemId): void
    {
        $item = Item::find($itemId);
        if (!$item || $item->cost_calculation !== Item::COST_WEIGHTED_AVERAGE) {
            return;
        }

        $currentItemPrice = self::getCurrentPrice($itemId);
        if (!$currentItemPrice) {
            return;
        }

        $computed     = self::computeCorrectPrice($itemId, $item->cost_calculation);
        if ($computed === null) {
            return;
        }

        $currentItemPrice->update(['price_usd' => $computed['price']]);
    }

    /**
     * Mark a purchase item's price history entry as removed.
     * Called when a purchase item is deleted or replaced.
     */
    public static function markPurchaseItemRemoved(PurchaseItem $purchaseItem): void
    {
        ItemPriceHistory::where('item_id', $purchaseItem->item_id)
            ->where('source_type', 'purchase_item')
            ->where('source_id', $purchaseItem->id)
            ->update([
                'is_current' => false,
                'note'       => 'Removed by user — no longer valid',
            ]);
    }

    /**
     * Determine whether a new price history entry should be marked as current.
     * Weighted average: always current (latest calculation wins).
     * Last cost: only current if this purchase item is the newest delivered purchase for the item.
     */
    private static function shouldBeCurrentPrice(PurchaseItem $purchaseItem, string $costCalculation): bool
    {
        if ($costCalculation === Item::COST_WEIGHTED_AVERAGE) {
            return true;
        }

        // Last cost: check if this purchase item belongs to the newest delivered purchase for the item
        $newestPurchaseItemId = PurchaseItem::where('item_id', $purchaseItem->item_id)
            ->join('purchases', 'purchase_items.purchase_id', '=', 'purchases.id')
            ->where('purchases.status', 'Delivered')
            ->whereNull('purchase_items.deleted_at')
            ->whereNull('purchases.deleted_at')
            ->orderByDesc('purchases.date')
            ->orderByDesc('purchases.id')
            ->orderByDesc('purchase_items.id')
            ->value('purchase_items.id');

        return $newestPurchaseItemId === $purchaseItem->id;
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
     * Build descriptive note for purchase price updates
     */
    private static function buildPurchaseUpdateNote(Purchase $purchase, PurchaseItem $purchaseItem, bool $isUpdate, ?float $oldCostPerItemUsd, ?int $oldQuantity): string
    {
        if (!$isUpdate) {
            return "Purchase #{$purchase->id} - {$purchaseItem->quantity} units @ \${$purchaseItem->cost_per_item_usd}/unit";
        }

        // For updates, show what changed
        $changes = [];

        if ($oldQuantity !== null && $oldQuantity != $purchaseItem->quantity) {
            $changes[] = "qty: {$oldQuantity} → {$purchaseItem->quantity}";
        }

        if ($oldCostPerItemUsd !== null && $oldCostPerItemUsd != $purchaseItem->cost_per_item_usd) {
            $currency  = FeatureHelper::isMultiCurrency() ? '$' : '';
            $changes[] = "cost: {$currency}{$oldCostPerItemUsd} → {$currency}{$purchaseItem->cost_per_item_usd}";
        }

        $changeText = !empty($changes) ? ' (' . implode(', ', $changes) . ')' : '';

        return "Updated Purchase #{$purchase->id}{$changeText}";
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
        // Get the item to check for starting inventory
        $item = Item::find($itemId);
        $startingQuantity = $item && $item->starting_quantity > 0 ? $item->starting_quantity : 0;
        $startingPrice = $item && $item->starting_price > 0 ? $item->starting_price : 0;

        // Get all OTHER delivered purchase items for this item (non-delivered stock is not in warehouse)
        $otherPurchases = \App\Models\Suppliers\PurchaseItem::where('purchase_items.item_id', $itemId)
            ->where('purchase_items.id', '!=', $excludePurchaseItemId)
            ->whereNull('purchase_items.deleted_at')
            ->join('purchases', 'purchase_items.purchase_id', '=', 'purchases.id')
            ->where('purchases.status', 'Delivered')
            ->whereNull('purchases.deleted_at')
            ->get(['purchase_items.quantity', 'purchase_items.cost_per_item_usd']);

        // Calculate total value and quantity from other purchases AND starting inventory
        $totalValueFromOthers = 0;
        $totalQtyFromOthers = 0;

        // Include starting inventory in calculations
        if ($startingQuantity > 0 && $startingPrice > 0) {
            $totalValueFromOthers += ($startingQuantity * $startingPrice);
            $totalQtyFromOthers += $startingQuantity;
        }

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
    private static function createPrice(int $itemId, float $priceUsd, string $effectiveDate, ?string $reason = null, ?string $sourceType = null, ?int $sourceId = null, ?string $calculationType = null): ItemPrice
    {
        return DB::transaction(function () use ($itemId, $priceUsd, $effectiveDate, $reason, $sourceType, $sourceId, $calculationType) {
            // Mark any existing is_current entries as no longer current
            ItemPriceHistory::where('item_id', $itemId)->where('is_current', true)->update(['is_current' => false]);

            $history = ItemPriceHistory::create([
                'item_id'                => $itemId,
                'price_usd'              => $priceUsd,
                'average_weighted_price' => $priceUsd,
                'latest_price'           => 0,
                'effective_date'         => $effectiveDate,
                'source_type'            => $sourceType ?? 'initial',
                'source_id'              => $sourceId,
                'note'                   => $reason ?? 'Initial price',
                'is_current'             => true,
                'calculation_type'       => $calculationType,
            ]);

            $itemPrice = ItemPrice::create([
                'item_id'          => $itemId,
                'price_usd'        => $priceUsd,
                'effective_date'   => $effectiveDate,
                'price_history_id' => $history->id,
            ]);

            return $itemPrice;
        });
    }

    /**
     * Update existing item price
     */
    private static function updatePrice(int $itemId, float $newPriceUsd, string $effectiveDate, ?float $oldPriceUsd = null, ?string $reason = null, ?string $sourceType = null, ?int $sourceId = null, ?string $calculationType = null, bool $isCurrent = true): ItemPrice
    {
        return DB::transaction(function () use ($itemId, $newPriceUsd, $effectiveDate, $oldPriceUsd, $reason, $sourceType, $sourceId, $calculationType, $isCurrent) {
            $itemPrice = ItemPrice::byItem($itemId)->first();
            $oldPrice  = $oldPriceUsd ?? $itemPrice->price_usd;

            if ($isCurrent) {
                // Clear previous is_current flag before creating the new entry
                ItemPriceHistory::where('item_id', $itemId)->where('is_current', true)->update(['is_current' => false]);
            }

            $history = ItemPriceHistory::create([
                'item_id'                => $itemId,
                'price_usd'              => $newPriceUsd,
                'average_weighted_price' => $newPriceUsd,
                'latest_price'           => $oldPrice,
                'effective_date'         => $effectiveDate,
                'source_type'            => $sourceType ?? 'manual',
                'source_id'              => $sourceId,
                'note'                   => $reason ?? 'Price update',
                'is_current'             => $isCurrent,
                'calculation_type'       => $calculationType,
            ]);

            if ($isCurrent) {
                $itemPrice->update([
                    'price_usd'        => $newPriceUsd,
                    'effective_date'   => $effectiveDate,
                    'price_history_id' => $history->id,
                ]);
            }

            return $itemPrice;
        });
    }


    /**
     * Clean up price history and restore previous price when a purchase is deleted
     */
    public static function deleteFromPurchase(Purchase $purchase, PurchaseItem $purchaseItem): void
    {
        // Mark history entry as removed (immutable — do not delete price data)
        self::markPurchaseItemRemoved($purchaseItem);

        // Restore current price from the most recent still-valid history entry
        self::restorePriceFromHistory($purchaseItem->item_id, $purchase->date);
    }

    /**
     * Clean up price history and restore previous price when a purchase return is deleted
     */
    public static function deleteFromPurchaseReturn(\App\Models\Suppliers\PurchaseReturn $purchaseReturn, \App\Models\Suppliers\PurchaseReturnItem $purchaseReturnItem): void
    {
        // Soft delete price history entries for this purchase return
        ItemPriceHistory::where('item_id', $purchaseReturnItem->item_id)
            ->where('source_type', 'purchase_return')
            ->where('source_id', $purchaseReturn->id)
            ->delete();

        // Restore previous price from price history
        self::restorePriceFromHistory($purchaseReturnItem->item_id, $purchaseReturn->date);
    }

    /**
     * Repair a single item's current price to match its latest price history entry.
     * Safe to call at any time — idempotent.
     */
    public static function repairItemPrice(int $itemId): void
    {
        self::restorePriceFromHistory($itemId, now()->toDateString());
    }

    /**
     * Restore item price from the most recent price history record
     * Used after deleting a purchase or purchase return
     */
    private static function restorePriceFromHistory(int $itemId, string $effectiveDate): void
    {
        // Find the most recent still-valid history entry (not marked removed)
        $previousPriceHistory = ItemPriceHistory::where('item_id', $itemId)
            ->where('note', '!=', 'Removed by user — no longer valid')
            ->orderBy('effective_date', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        // Clear all is_current flags first
        ItemPriceHistory::where('item_id', $itemId)->where('is_current', true)->update(['is_current' => false]);

        if ($previousPriceHistory) {
            ItemPrice::where('item_id', $itemId)->update([
                'price_usd'        => $previousPriceHistory->price_usd,
                'effective_date'   => $previousPriceHistory->effective_date,
                'price_history_id' => $previousPriceHistory->id,
            ]);

            $previousPriceHistory->update(['is_current' => true]);
        } else {
            $item          = Item::find($itemId);
            $fallbackPrice = ($item && $item->starting_price > 0) ? $item->starting_price : 0;

            ItemPrice::where('item_id', $itemId)->update([
                'price_usd'      => $fallbackPrice,
                'effective_date' => $effectiveDate,
            ]);
        }
    }

    /**
     * Restore price history and recalculate price when a purchase is restored
     */
    public static function restoreFromPurchase(Purchase $purchase, PurchaseItem $purchaseItem): void
    {
        // Restore soft-deleted price history entries for this purchase item
        ItemPriceHistory::where('item_id', $purchaseItem->item_id)
            ->where('source_type', 'purchase_item')
            ->where('source_id', $purchaseItem->id)
            ->onlyTrashed()
            ->restore();

        // Update current price to the most recent price history (including just restored)
        $latestPriceHistory = ItemPriceHistory::where('item_id', $purchaseItem->item_id)
            ->orderBy('effective_date', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        if ($latestPriceHistory) {
            $currentItemPrice = self::getCurrentPrice($purchaseItem->item_id);
            if ($currentItemPrice) {
                $currentItemPrice->update([
                    'price_usd' => $latestPriceHistory->price_usd,
                    'effective_date' => $latestPriceHistory->effective_date,
                ]);
            }
        }
    }

    /**
     * Restore price history and recalculate price when a purchase return is restored
     */
    public static function restoreFromPurchaseReturn(\App\Models\Suppliers\PurchaseReturn $purchaseReturn, \App\Models\Suppliers\PurchaseReturnItem $purchaseReturnItem): void
    {
        // Restore soft-deleted price history entries for this purchase return
        ItemPriceHistory::where('item_id', $purchaseReturnItem->item_id)
            ->where('source_type', 'purchase_return')
            ->where('source_id', $purchaseReturn->id)
            ->onlyTrashed()
            ->restore();

        // Update current price to the most recent price history (including just restored)
        $latestPriceHistory = ItemPriceHistory::where('item_id', $purchaseReturnItem->item_id)
            ->orderBy('effective_date', 'desc')
            ->orderBy('id', 'desc')
            ->first();

        if ($latestPriceHistory) {
            $currentItemPrice = self::getCurrentPrice($purchaseReturnItem->item_id);
            if ($currentItemPrice) {
                $currentItemPrice->update([
                    'price_usd' => $latestPriceHistory->price_usd,
                    'effective_date' => $latestPriceHistory->effective_date,
                ]);
            }
        }
    }

    /**
     * Check if item can have its starting price changed
     */
    public static function canUpdateStartingPrice(int $itemId): bool
    {
        // Check if any purchases exist
        $hasPurchases = PurchaseItem::where('item_id', $itemId)->exists();

        // Check if any price history exists (excluding initial entries)
        // Allow updating starting price if only 'initial' price history entries exist
        $hasNonInitialPriceHistory = ItemPriceHistory::where('item_id', $itemId)
            ->where('source_type', '!=', 'initial')
            ->exists();

        // Check if any inventory adjustments exist (future stock adjustment module)
        // $hasAdjustments = DB::table('inventory_adjustments')->where('item_id', $itemId)->exists();

        return !$hasPurchases && !$hasNonInitialPriceHistory; // && !$hasAdjustments
    }

    /**
     * Get starting price change impact details
     */
    public static function getStartingPriceChangeImpact(int $itemId): array
    {
        $purchaseCount = PurchaseItem::where('item_id', $itemId)->count();

        // Get all price history counts in one query using selectRaw
        $priceHistoryCounts = ItemPriceHistory::where('item_id', $itemId)
            ->selectRaw('
                COUNT(*) as total_count,
                SUM(CASE WHEN source_type != "initial" THEN 1 ELSE 0 END) as non_initial_count
            ')
            ->first();

        $priceHistoryCount = $priceHistoryCounts->total_count ?? 0;
        $nonInitialPriceHistoryCount = $priceHistoryCounts->non_initial_count ?? 0;

        $totalTransactions = $purchaseCount + $nonInitialPriceHistoryCount;

        return [
            'can_change' => $totalTransactions === 0,
            'purchase_count' => $purchaseCount,
            'price_history_count' => $priceHistoryCount,
            'non_initial_price_history_count' => $nonInitialPriceHistoryCount,
            'total_transactions' => $totalTransactions,
            'warning_message' => $totalTransactions > 0
                ? "Cannot change starting price. This item has {$totalTransactions} transaction(s) that would be affected."
                : null
        ];
    }

    /**
     * One-time repair: fixes stale item_prices and supplier_item_prices caused by
     * purchase items deleted before the price-cleanup bug was fixed.
     *
     * Usage: call once from any controller action, then remove the call.
     * Example: PriceService::reindexPurchasePrices();
     *
     * Returns array of fixed item_ids and supplier_item_price ids for verification.
     */
    public static function reindexPurchasePrices(): array
    {
        $fixed = ['item_prices' => [], 'supplier_item_prices' => []];

        // Fix item_prices: sync each item's current price to latest price history
        $itemPrices = ItemPrice::all();
        foreach ($itemPrices as $itemPrice) {
            $latestHistory = ItemPriceHistory::where('item_id', $itemPrice->item_id)
                ->orderBy('effective_date', 'desc')
                ->orderBy('id', 'desc')
                ->first();

            if ($latestHistory && (float) $latestHistory->price_usd !== (float) $itemPrice->price_usd) {
                $itemPrice->update([
                    'price_usd'      => $latestHistory->price_usd,
                    'effective_date' => $latestHistory->effective_date,
                ]);
                $fixed['item_prices'][] = $itemPrice->item_id;
            } elseif (!$latestHistory && (float) $itemPrice->price_usd !== 0.0) {
                // No price history left — reset price to zero
                $itemPrice->update([
                    'price_usd'      => 0,
                    'effective_date' => now()->toDateString(),
                ]);
                $fixed['item_prices'][] = $itemPrice->item_id;
            }
        }

        // Fix supplier_item_prices: soft-delete orphaned records where the
        // linked purchase no longer contains that item, then restore previous
        $supplierPrices = SupplierItemPrice::whereNotNull('last_purchase_id')->get();
        foreach ($supplierPrices as $supplierPrice) {
            $itemStillInPurchase = PurchaseItem::where('item_id', $supplierPrice->item_id)
                ->where('purchase_id', $supplierPrice->last_purchase_id)
                ->exists();

            if (!$itemStillInPurchase) {
                $supplierPrice->delete();

                $previous = SupplierItemPrice::where('supplier_id', $supplierPrice->supplier_id)
                    ->where('item_id', $supplierPrice->item_id)
                    ->orderBy('last_purchase_date', 'desc')
                    ->orderBy('id', 'desc')
                    ->first();

                if ($previous) {
                    SupplierItemPriceService::markOthersAsNotCurrent(
                        $supplierPrice->supplier_id,
                        $supplierPrice->item_id,
                        $previous->id
                    );
                    $previous->update(['is_current' => true]);
                }

                $fixed['supplier_item_prices'][] = $supplierPrice->id;
            }
        }

        return $fixed;
    }

    /**
     * Scan all items with delivered purchases and report price discrepancies.
     * No data is modified. Safe to call anytime — including from a scheduler or job.
     */
    public static function auditItemPrices(float $tolerance = 2.0): array
    {
        $items   = Item::whereIn('id', self::itemIdsWithDeliveredPurchases())->with('itemPrice')->get();
        $preview = [];
        $missing = [];

        foreach ($items as $item) {
            $computed     = self::computeCorrectPrice($item->id, $item->cost_calculation);
            if ($computed === null) continue;

            $correctPrice = $computed['price'];

            $currentPrice      = $item->itemPrice ? (float) $item->itemPrice->price_usd : null;
            $difference        = $currentPrice !== null ? round(abs($correctPrice - $currentPrice), 6) : null;
            $diffPercentValue  = ($currentPrice !== null && $currentPrice > 0)
                ? round(abs($correctPrice - $currentPrice) / $currentPrice * 100, 2)
                : null;
            $diffPercent       = $diffPercentValue !== null
                ? $diffPercentValue . '%'
                : ($currentPrice === 0.0 ? 'N/A' : null);

            $row = [
                'item_id'        => $item->id,
                'item_code'      => $item->code,
                'item_name'      => $item->short_name,
                'description'    => $item->description,
                'calc_method'    => $item->cost_calculation,
                'current_price'  => $currentPrice,
                'correct_price'  => round($correctPrice, 6),
                'difference'     => $difference,
                'diff_percent'   => $diffPercent,
                'last_purchase'  => $item->cost_calculation === Item::COST_LAST_COST
                    ? ['purchase_id' => $computed['purchase_id'], 'purchase_code' => $computed['purchase_code'], 'price' => $computed['price'], 'purchase_date' => $computed['purchase_date']]
                    : null,
            ];

            if (!$item->itemPrice) {
                $missing[] = $row;
                continue;
            }

            if ($difference <= 0.000001) continue;
            if ($diffPercentValue !== null && $diffPercentValue <= $tolerance) continue;

            $preview[] = $row;
        }

        usort($preview, fn($a, $b) => (float) $b['diff_percent'] <=> (float) $a['diff_percent']);
        usort($missing, fn($a, $b) => $b['correct_price'] <=> $a['correct_price']);

        return [
            'total_items_checked' => $items->count(),
            'items_to_fix'        => count($preview),
            'items_missing_price' => count($missing),
            'tolerance_percent'   => $tolerance,
            'changes'             => $preview,
            'missing'             => $missing,
        ];
    }

    /**
     * Scan all items with delivered purchases, correct wrong prices, and populate missing records.
     * Idempotent — safe to call anytime, including from a scheduler or job.
     */
    public static function auditAndFixItemPrices(float $tolerance = 2.0): array
    {
        $result = ['fixed' => [], 'created' => [], 'skipped' => []];

        $items = Item::whereIn('id', self::itemIdsWithDeliveredPurchases())->with('itemPrice')->get();

        foreach ($items as $item) {
            $computed = self::computeCorrectPrice($item->id, $item->cost_calculation);

            if ($computed === null) {
                $result['skipped'][] = $item->id;
                continue;
            }

            if ($tolerance > 0 && $item->itemPrice) {
                $currentPrice = (float) $item->itemPrice->price_usd;
                if ($currentPrice > 0) {
                    $diffPercent = abs($computed['price'] - $currentPrice) / $currentPrice * 100;
                    if ($diffPercent <= $tolerance) {
                        $result['skipped'][] = $item->id;
                        continue;
                    }
                }
            }

            self::fixOneItem($item, $computed, $result);
        }

        return $result;
    }

    public static function auditSingleItemPrice(int $itemId, float $tolerance = 2.0): array
    {
        $item = Item::with('itemPrice')->find($itemId);

        if (!$item) {
            return ['error' => 'Item not found.'];
        }

        $computed = self::computeCorrectPrice($item->id, $item->cost_calculation);

        if ($computed === null) {
            return ['status' => 'skipped', 'reason' => 'No delivered purchases found for this item.'];
        }

        $correctPrice = $computed['price'];

        $currentPrice     = $item->itemPrice ? (float) $item->itemPrice->price_usd : null;
        $difference       = $currentPrice !== null ? round(abs($correctPrice - $currentPrice), 6) : null;
        $diffPercentValue = ($currentPrice !== null && $currentPrice > 0)
            ? round(abs($correctPrice - $currentPrice) / $currentPrice * 100, 2)
            : null;
        $diffPercent      = $diffPercentValue !== null
            ? $diffPercentValue . '%'
            : ($currentPrice === 0.0 ? 'N/A' : null);

        $base = [
            'item_id'           => $item->id,
            'item_code'         => $item->code,
            'item_name'         => $item->short_name,
            'description'       => $item->description,
            'calc_method'       => $item->cost_calculation,
            'current_price'     => $currentPrice,
            'correct_price'     => round($correctPrice, 6),
            'difference'        => $difference,
            'diff_percent'      => $diffPercent,
            'tolerance_percent' => $tolerance,
            'last_purchase'  => $item->cost_calculation === Item::COST_LAST_COST
                    ? ['purchase_id' => $computed['purchase_id'], 'purchase_code' => $computed['purchase_code'], 'price' => $computed['price'], 'purchase_date' => $computed['purchase_date']]
                    : null,
        ];

        if (!$item->itemPrice) {
            return array_merge($base, ['status' => 'missing']);
        }

        if (abs($correctPrice - $currentPrice) <= 0.000001) {
            return array_merge($base, ['status' => 'ok']);
        }

        if ($diffPercentValue !== null && $diffPercentValue <= $tolerance) {
            return array_merge($base, ['status' => 'within_tolerance']);
        }

        return array_merge($base, ['status' => 'wrong']);
    }

    public static function auditAndFixSingleItemPrice(int $itemId, float $tolerance = 2.0): array
    {
        $item = Item::with('itemPrice')->find($itemId);

        if (!$item) {
            return ['error' => 'Item not found.'];
        }

        $result   = ['fixed' => [], 'created' => [], 'skipped' => []];
        $computed = self::computeCorrectPrice($item->id, $item->cost_calculation);

        if ($computed === null) {
            $result['skipped'][] = $item->id;
            return $result;
        }

        if ($tolerance > 0 && $item->itemPrice) {
            $currentPrice = (float) $item->itemPrice->price_usd;
            if ($currentPrice > 0) {
                $diffPercent = abs($computed['price'] - $currentPrice) / $currentPrice * 100;
                if ($diffPercent <= $tolerance) {
                    $result['skipped'][] = $item->id;
                    return $result;
                }
            }
        }

        self::fixOneItem($item, $computed, $result);

        return $result;
    }

    private static function fixOneItem(Item $item, array $computed, array &$result): void
    {
        $purchaseItems = PurchaseItem::where('purchase_items.item_id', $item->id)
            ->join('purchases', 'purchase_items.purchase_id', '=', 'purchases.id')
            ->where('purchases.status', 'Delivered')
            ->whereNull('purchase_items.deleted_at')
            ->whereNull('purchases.deleted_at')
            ->orderBy('purchases.date', 'asc')
            ->orderBy('purchases.id', 'asc')
            ->orderBy('purchase_items.id', 'asc')
            ->select('purchase_items.*', 'purchases.date as purchase_date')
            ->get();

        if ($purchaseItems->isEmpty()) {
            $result['skipped'][] = $item->id;
            return;
        }

        DB::transaction(function () use ($item, $purchaseItems, $computed, &$result) {
            $existingBySourceId = ItemPriceHistory::where('item_id', $item->id)
                ->where('source_type', 'purchase_item')
                ->get()
                ->keyBy('source_id');

            $updated     = 0;
            $lastHistory = null;

            foreach ($purchaseItems as $pi) {
                $correctPrice = round((float) $pi->cost_per_item_usd, 4);
                $existing     = $existingBySourceId->get($pi->id);

                if ($existing) {
                    if (abs((float) $existing->price_usd - $correctPrice) > 0.000001) {
                        $existing->timestamps = false;
                        $existing->update([
                            'price_usd'              => $correctPrice,
                            'average_weighted_price' => $correctPrice,
                            'effective_date'         => $pi->purchase_date,
                            'created_at'             => $pi->created_at,
                            'updated_at'             => $pi->updated_at,
                            'note'                   => "Price corrected from {$existing->price_usd} to {$correctPrice} [Audited]",
                        ]);
                        $updated++;
                    }
                    $lastHistory = $existing;
                } else {
                    $history             = new ItemPriceHistory([
                        'item_id'                => $item->id,
                        'price_usd'              => $correctPrice,
                        'average_weighted_price' => $correctPrice,
                        'latest_price'           => 0,
                        'effective_date'         => $pi->purchase_date,
                        'source_type'            => 'purchase_item',
                        'source_id'              => $pi->id,
                        'note'                   => "Price {$correctPrice} from purchase [Audited]",
                        'is_current'             => false,
                        'calculation_type'       => $item->cost_calculation,
                    ]);
                    $history->created_at = $pi->created_at;
                    $history->updated_at = $pi->updated_at;
                    $history->save();
                    $lastHistory = $history;
                    $updated++;
                }
            }

            if ($item->itemPrice) {
                $item->itemPrice->update([
                    'price_usd'        => $computed['price'],
                    'effective_date'   => $computed['purchase_date'],
                    'price_history_id' => $lastHistory?->id,
                ]);
            } else {
                ItemPrice::create([
                    'item_id'          => $item->id,
                    'price_usd'        => $computed['price'],
                    'effective_date'   => $computed['purchase_date'],
                    'price_history_id' => $lastHistory?->id,
                ]);
            }

            if ($updated === 0) {
                $result['skipped'][] = $item->id;
                return;
            }

            $result['fixed'][$item->id] = [
                'item_code'   => $item->code,
                'item_name'   => $item->short_name,
                'description' => $item->description,
                'final_price' => $computed['price'],
                'calc_method' => $item->cost_calculation,
            ];
        });
    }

    private static function itemIdsWithDeliveredPurchases(): array
    {
        return PurchaseItem::join('purchases', 'purchase_items.purchase_id', '=', 'purchases.id')
            ->where('purchases.status', 'Delivered')
            ->whereNull('purchase_items.deleted_at')
            ->whereNull('purchases.deleted_at')
            ->pluck('purchase_items.item_id')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Compute the correct current price for an item from delivered purchase items.
     * Returns ['price' => float, 'purchase_id' => int|null, 'purchase_date' => string|null],
     * or null if no delivered purchases exist.
     */
    private static function computeCorrectPrice(int $itemId, string $costCalculation): ?array
    {
        if ($costCalculation === Item::COST_LAST_COST) {
            $latest = PurchaseItem::where('purchase_items.item_id', $itemId)
                ->join('purchases', 'purchase_items.purchase_id', '=', 'purchases.id')
                ->where('purchases.status', 'Delivered')
                ->whereNull('purchase_items.deleted_at')
                ->whereNull('purchases.deleted_at')
                ->orderByDesc('purchases.id')
                ->orderByDesc('purchase_items.id')
                ->first(['purchase_items.cost_per_item_usd', 'purchases.id as purchase_id', 'purchases.date as purchase_date', 'purchases.prefix as purchase_prefix', 'purchases.code as purchase_code']);

            return $latest ? [
                'price'         => (float) $latest->cost_per_item_usd,
                'purchase_id'   => $latest->purchase_id,
                'purchase_code' => $latest->purchase_prefix . $latest->purchase_code,
                'purchase_date' => $latest->purchase_date,
            ] : null;
        }

        // Weighted average: recompute from delivered purchases in chronological order.
        $deliveredItems = PurchaseItem::where('purchase_items.item_id', $itemId)
            ->join('purchases', 'purchase_items.purchase_id', '=', 'purchases.id')
            ->where('purchases.status', 'Delivered')
            ->whereNull('purchase_items.deleted_at')
            ->whereNull('purchases.deleted_at')
            ->orderBy('purchases.date', 'asc')
            ->orderBy('purchases.id', 'asc')
            ->orderBy('purchase_items.id', 'asc')
            ->get(['purchase_items.quantity', 'purchase_items.cost_per_item_usd', 'purchases.date']);

        if ($deliveredItems->isEmpty()) {
            return null;
        }

        $item       = Item::find($itemId);
        $totalQty   = ($item && $item->starting_quantity > 0) ? (float) $item->starting_quantity : 0.0;
        $totalValue = ($item && $item->starting_price > 0)    ? $totalQty * (float) $item->starting_price : 0.0;

        foreach ($deliveredItems as $pi) {
            $totalQty   += (float) $pi->quantity;
            $totalValue += (float) $pi->quantity * (float) $pi->cost_per_item_usd;
        }

        $price = $totalQty > 0 ? ($totalValue / $totalQty) : null;

        return $price !== null ? ['price' => $price, 'purchase_id' => null, 'purchase_date' => null] : null;
    }

}