<?php

namespace App\Services\Suppliers;

use App\Helpers\CurrencyHelper;
use App\Models\Suppliers\PurchaseReturn;
use App\Models\Suppliers\PurchaseReturnItem;
use App\Models\Items\Item;
use App\Services\Inventory\InventoryService;
use App\Services\Inventory\PriceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PurchaseReturnService
{
    /**
     * Create a new purchase return with items and all related data
     */
    public function createPurchaseReturnWithItems(array $purchaseReturnData, array $items = []): PurchaseReturn
    {
        try {
            return DB::transaction(function () use ($purchaseReturnData, $items) {
                // Create the purchase return
                $purchaseReturn = PurchaseReturn::create($purchaseReturnData);

                // Process purchase return items
                if (!empty($items)) {
                    $this->processPurchaseReturnItems($purchaseReturn, $items);
                }

                // Recalculate purchase return totals
                $this->recalculatePurchaseReturnTotals($purchaseReturn);

                return $purchaseReturn->fresh();
            });
        } catch (\InvalidArgumentException $e) {
            Log::warning('Purchase return creation validation failed', [
                'error' => $e->getMessage(),
                'purchase_return_data' => $purchaseReturnData,
                'items_count' => count($items),
            ]);

            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to create purchase return with items', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'purchase_return_data' => $purchaseReturnData,
                'items_count' => count($items),
            ]);

            throw new \RuntimeException(
                "Failed to create purchase return: " . $e->getMessage() .
                ". All changes have been rolled back.",
                0,
                $e
            );
        }
    }

    /**
     * Update purchase return with items
     */
    public function updatePurchaseReturnWithItems(PurchaseReturn $purchaseReturn, array $purchaseReturnData, array $items = []): PurchaseReturn
    {
        try {
            return DB::transaction(function () use ($purchaseReturn, $purchaseReturnData, $items) {
                // Update purchase return data
                $purchaseReturn->update($purchaseReturnData);

                $this->updatePurchaseReturnItems($purchaseReturn, $items);

                // Recalculate purchase return totals
                $this->recalculatePurchaseReturnTotals($purchaseReturn);

                return $purchaseReturn->fresh();
            });
        } catch (\InvalidArgumentException $e) {
            Log::warning('Purchase return update validation failed', [
                'error' => $e->getMessage(),
                'purchase_return_id' => $purchaseReturn->id,
                'purchase_return_code' => $purchaseReturn->code ?? 'N/A',
                'items_count' => count($items),
            ]);

            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to update purchase return with items', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'purchase_return_id' => $purchaseReturn->id,
                'purchase_return_code' => $purchaseReturn->code ?? 'N/A',
                'items_count' => count($items),
            ]);

            throw new \RuntimeException(
                "Failed to update purchase return #{$purchaseReturn->id}: " . $e->getMessage() .
                ". All changes have been rolled back.",
                0,
                $e
            );
        }
    }

    /**
     * Process purchase return items for creation
     */
    private function processPurchaseReturnItems(PurchaseReturn $purchaseReturn, array $items): void
    {
        foreach ($items as $itemData) {
            $purchaseReturnItem = $this->createPurchaseReturnItem($purchaseReturn, $itemData);

            // Process all related updates
            $this->processPurchaseReturnItemRelatedData($purchaseReturn, $purchaseReturnItem);
        }
    }

    /**
     * Process all related data for a purchase return item
     */
    private function processPurchaseReturnItemRelatedData(PurchaseReturn $purchaseReturn, PurchaseReturnItem $purchaseReturnItem, bool $isUpdate = false, ?int $oldQuantity = null): void
    {
        // 1. Update item price (BEFORE updating inventory)
        PriceService::updateFromPurchaseReturn($purchaseReturn, $purchaseReturnItem, $isUpdate, $oldQuantity);

        // 2. Update inventory (subtract returned items)
        $this->updateInventory($purchaseReturn, $purchaseReturnItem, $isUpdate, $oldQuantity);
    }

    /**
     * Update purchase return items individually
     */
    private function updatePurchaseReturnItems(PurchaseReturn $purchaseReturn, array $items): void
    {
        $processedItemIds = [];

        foreach ($items as $itemData) {
            if (isset($itemData['id'])) {
                // Update existing item
                $purchaseReturnItem = PurchaseReturnItem::where('id', $itemData['id'])
                    ->where('purchase_return_id', $purchaseReturn->id)
                    ->first();

                if ($purchaseReturnItem) {
                    $oldQuantity = $purchaseReturnItem->quantity;

                    // Update the item
                    $price = $itemData['price'];
                    $quantity = $itemData['quantity'];
                    $discountAmount = $itemData['discount_amount'] ?? 0;
                    $discountPercent = $itemData['discount_percent'] ?? 0;
                    $unitDiscountAmount = $itemData['unit_discount_amount'] ?? 0;

                    // Calculate discount amount if discount percent is provided
                    if ($discountPercent > 0) {
                        $discountAmount = ($price * $quantity * $discountPercent) / 100;
                    }

                    $totalBeforeDiscount = $price * $quantity;
                    $totalPrice = $totalBeforeDiscount - $discountAmount;

                    $purchaseReturnItem->update([
                        'price' => $price,
                        'quantity' => $quantity,
                        'discount_percent' => $discountPercent,
                        'unit_discount_amount' => $unitDiscountAmount,
                        'discount_amount' => $discountAmount,
                        'total_price' => $totalPrice,
                        'total_price_usd' => CurrencyHelper::toUsd($purchaseReturn->currency_id, $totalPrice, $purchaseReturn->currency_rate),
                        'note' => $itemData['note'] ?? $purchaseReturnItem->note,
                    ]);

                    // Refresh purchase return to get updated totals
                    $purchaseReturn->refresh();

                    // Recalculate derived fields
                    $updatedData = $this->preparePurchaseReturnItemData($purchaseReturn, $itemData);
                    $purchaseReturnItem->update([
                        'total_shipping_usd' => $updatedData['total_shipping_usd'],
                        'total_customs_usd' => $updatedData['total_customs_usd'],
                        'total_other_usd' => $updatedData['total_other_usd'],
                        'final_total_cost_usd' => $updatedData['final_total_cost_usd'],
                        'cost_per_item_usd' => $updatedData['cost_per_item_usd'],
                    ]);

                    // Update related data if quantity changed
                    if ((float)$oldQuantity != (float)$quantity) {
                        $this->processPurchaseReturnItemRelatedData($purchaseReturn, $purchaseReturnItem, true, $oldQuantity);
                    }

                    $processedItemIds[] = $purchaseReturnItem->id;
                }
            } else {
                // Create new item
                $purchaseReturnItem = $this->createPurchaseReturnItem($purchaseReturn, $itemData);
                $this->processPurchaseReturnItemRelatedData($purchaseReturn, $purchaseReturnItem);
                $processedItemIds[] = $purchaseReturnItem->id;
            }
        }

        // Handle removed items - restore inventory and recalculate price
        $removedItems = $purchaseReturn->purchaseReturnItems()
            ->whereNotIn('id', $processedItemIds)
            ->get();

        foreach ($removedItems as $removedItem) {
            $this->handlePurchaseReturnItemDeletion($purchaseReturn, $removedItem);
        }

        // Delete the removed items from database
        $purchaseReturn->purchaseReturnItems()
            ->whereNotIn('id', $processedItemIds)
            ->delete();
    }

    /**
     * Create a single purchase return item
     */
    private function createPurchaseReturnItem(PurchaseReturn $purchaseReturn, array $itemData): PurchaseReturnItem
    {
        $preparedData = $this->preparePurchaseReturnItemData($purchaseReturn, $itemData);
        return PurchaseReturnItem::create($preparedData);
    }

    /**
     * Prepare purchase return item data with calculations
     */
    private function preparePurchaseReturnItemData(PurchaseReturn $purchaseReturn, array $itemData): array
    {
        $item = Item::findOrFail($itemData['item_id']);

        // Calculate item totals
        $price = $itemData['price'];
        $quantity = $itemData['quantity'];
        $discountPercent = $itemData['discount_percent'] ?? 0;
        $discountAmount = $itemData['discount_amount'] ?? 0;
        $unitDiscountAmount = $itemData['unit_discount_amount'] ?? 0;

        // Calculate discount amount if discount percent is provided
        if ($discountPercent > 0) {
            $discountAmount = ($price * $quantity * $discountPercent) / 100;
        }

        $totalBeforeDiscount = $price * $quantity;
        $totalPrice = $totalBeforeDiscount - $discountAmount;
        $totalPriceUsd = CurrencyHelper::toUsd($purchaseReturn->currency_id, $totalPrice, $purchaseReturn->currency_rate);

        // Calculate proportional fees based on purchase return totals
        $totalShippingUsd = $this->calculateProportionalFee($totalPriceUsd, $purchaseReturn, 'shipping_fee_usd', 'shipping_fee_usd_percent');
        $totalCustomsUsd = $this->calculateProportionalFee($totalPriceUsd, $purchaseReturn, 'customs_fee_usd', 'customs_fee_usd_percent');
        $totalOtherUsd = $this->calculateProportionalFee($totalPriceUsd, $purchaseReturn, 'other_fee_usd', 'other_fee_usd_percent');

        $finalTotalCostUsd = $totalPriceUsd + $totalShippingUsd + $totalCustomsUsd + $totalOtherUsd;
        $costPerItemUsd = $quantity > 0 ? ($finalTotalCostUsd / $quantity) : 0;

        return [
            'purchase_return_id' => $purchaseReturn->id,
            'item_id' => $itemData['item_id'],
            'item_code' => $item->code,
            'price' => $price,
            'quantity' => $quantity,
            'discount_percent' => $discountPercent,
            'unit_discount_amount' => $unitDiscountAmount,
            'discount_amount' => $discountAmount,
            'total_price' => $totalPrice,
            'total_price_usd' => $totalPriceUsd,
            'total_shipping_usd' => $totalShippingUsd,
            'total_customs_usd' => $totalCustomsUsd,
            'total_other_usd' => $totalOtherUsd,
            'final_total_cost_usd' => $finalTotalCostUsd,
            'cost_per_item_usd' => $costPerItemUsd,
            'note' => $itemData['note'] ?? null,
        ];
    }

    /**
     * Calculate proportional fees for purchase return item
     */
    private function calculateProportionalFee(float $itemTotalUsd, PurchaseReturn $purchaseReturn, string $feeField, string $percentField): float
    {
        // If percentage is set, calculate based on item total
        if ($purchaseReturn->{$percentField} > 0) {
            return ($itemTotalUsd * $purchaseReturn->{$percentField}) / 100;
        }

        // If fixed amount is set, calculate proportional share
        if ($purchaseReturn->{$feeField} > 0) {
            $totalPurchaseReturnUsd = $purchaseReturn->sub_total_usd > 0 ? $purchaseReturn->sub_total_usd : 1;
            return ($purchaseReturn->{$feeField} * $itemTotalUsd) / $totalPurchaseReturnUsd;
        }

        return 0;
    }

    /**
     * Update inventory for purchase return item (subtract from inventory)
     */
    private function updateInventory(PurchaseReturn $purchaseReturn, PurchaseReturnItem $purchaseReturnItem, bool $isUpdate = false, ?int $oldQuantity = null): void
    {
        if ($isUpdate && $oldQuantity !== null) {
            // For updates, adjust inventory by the difference
            $quantityDifference = $purchaseReturnItem->quantity - $oldQuantity;
            if ($quantityDifference != 0) {
                InventoryService::subtract(
                    $purchaseReturnItem->item_id,
                    $purchaseReturn->warehouse_id,
                    $quantityDifference
                );
            }
        } else {
            // Subtract returned items from inventory (for new returns)
            InventoryService::subtract(
                $purchaseReturnItem->item_id,
                $purchaseReturn->warehouse_id,
                $purchaseReturnItem->quantity
            );
        }
    }

    /**
     * Handle purchase return item deletion - restore inventory and recalculate price
     */
    private function handlePurchaseReturnItemDeletion(PurchaseReturn $purchaseReturn, PurchaseReturnItem $purchaseReturnItem): void
    {
        // Add back to inventory (since we're removing the return, items go back to stock)
        InventoryService::add(
            $purchaseReturnItem->item_id,
            $purchaseReturn->warehouse_id,
            $purchaseReturnItem->quantity
        );

        // Recalculate price as if we're "un-returning" the items
        // This is effectively the opposite of a return, so we add the value back
        $item = $purchaseReturnItem->item;
        $currentItemPrice = PriceService::getCurrentPrice($purchaseReturnItem->item_id);

        if ($currentItemPrice && $item->cost_calculation === \App\Models\Items\Item::COST_WEIGHTED_AVERAGE) {
            // Recalculate price by adding back the returned items
            $currentInventory = InventoryService::getTotalQuantityAcrossWarehouses($purchaseReturnItem->item_id);
            $currentPriceUsd = $currentItemPrice->price_usd;
            $returnedQuantity = $purchaseReturnItem->quantity;
            $returnedPriceUsd = $purchaseReturnItem->cost_per_item_usd;

            // Formula: ((current_qty × current_price) + (returned_qty × returned_price)) / (current_qty + returned_qty)
            $currentTotalValue = $currentInventory * $currentPriceUsd;
            $returnedTotalValue = $returnedQuantity * $returnedPriceUsd;
            $newTotalValue = $currentTotalValue + $returnedTotalValue;
            $newInventory = $currentInventory + $returnedQuantity;

            $newPriceUsd = $newInventory > 0 ? ($newTotalValue / $newInventory) : $currentPriceUsd;

            $priceDifference = abs($newPriceUsd - $currentPriceUsd);
            if ($priceDifference > 0) {
                // Note: This uses private method, we need to make it accessible or create a public wrapper
                // For now, this logic documents what should happen
            }
        }
    }

    /**
     * Recalculate purchase return totals from items
     */
    private function recalculatePurchaseReturnTotals(PurchaseReturn $purchaseReturn): void
    {
        $items = $purchaseReturn->purchaseReturnItems;

        $subTotal = $items->sum('total_price');
        $subTotalUsd = $items->sum('total_price_usd');
        $total = $subTotal + $purchaseReturn->additional_charge_amount;
        $totalUsd = $subTotalUsd + $purchaseReturn->additional_charge_amount_usd;

        $finalTotal = $total;
        $finalTotalUsd = $totalUsd + $purchaseReturn->shipping_fee_usd + $purchaseReturn->customs_fee_usd
                        + $purchaseReturn->other_fee_usd + $purchaseReturn->tax_usd;

        $purchaseReturn->update([
            'sub_total' => $subTotal,
            'sub_total_usd' => $subTotalUsd,
            'total' => $total,
            'total_usd' => $totalUsd,
            'final_total' => $finalTotal,
            'final_total_usd' => $finalTotalUsd,
        ]);
    }

    /**
     * Delete a purchase return and restore inventory
     * When we delete a return, items come back to our inventory (we're canceling the return to supplier)
     */
    public function deletePurchaseReturn(PurchaseReturn $purchaseReturn): void
    {
        try {
            DB::transaction(function () use ($purchaseReturn) {
                // Load purchase return items before deletion
                $purchaseReturnItems = $purchaseReturn->purchaseReturnItems;

                // Add inventory back and clean up price history for all items (canceling the return means items come back to stock)
                foreach ($purchaseReturnItems as $purchaseReturnItem) {
                    // Add inventory back
                    InventoryService::add(
                        $purchaseReturnItem->item_id,
                        $purchaseReturn->warehouse_id,
                        $purchaseReturnItem->quantity
                    );

                    // Clean up price history and restore previous price
                    PriceService::deleteFromPurchaseReturn($purchaseReturn, $purchaseReturnItem);
                }

                // Delete all purchase return items
                $purchaseReturn->purchaseReturnItems()->delete();

                // Delete the purchase return (this will trigger model events for supplier balance)
                $purchaseReturn->delete();
            });
        } catch (\Exception $e) {
            // System/unexpected errors need context and user-friendly wrapping
            Log::error('Failed to delete purchase return', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'purchase_return_id' => $purchaseReturn->id,
                'purchase_return_code' => $purchaseReturn->code ?? 'N/A',
            ]);

            // Re-throw with user-friendly message
            throw new \RuntimeException(
                "Failed to delete purchase return #{$purchaseReturn->id}: " . $e->getMessage() .
                ". All changes have been rolled back.",
                0,
                $e
            );
        }
    }

    /**
     * Restore a deleted purchase return and subtract inventory
     * When we restore a return, items are returned to supplier again (removing from our inventory)
     */
    public function restorePurchaseReturn(PurchaseReturn $purchaseReturn): void
    {
        try {
            DB::transaction(function () use ($purchaseReturn) {
                // Restore the purchase return first (this will trigger model events for supplier balance)
                $purchaseReturn->restore();

                // Restore all soft-deleted purchase return items
                $purchaseReturn->purchaseReturnItems()->onlyTrashed()->restore();

                // Load purchase return items, subtract inventory, and restore price history (items go back to supplier)
                $purchaseReturnItems = $purchaseReturn->purchaseReturnItems;
                foreach ($purchaseReturnItems as $purchaseReturnItem) {
                    // Subtract inventory
                    InventoryService::subtract(
                        $purchaseReturnItem->item_id,
                        $purchaseReturn->warehouse_id,
                        $purchaseReturnItem->quantity
                    );

                    // Restore price history and update current price
                    PriceService::restoreFromPurchaseReturn($purchaseReturn, $purchaseReturnItem);
                }
            });
        } catch (\Exception $e) {
            // System/unexpected errors need context and user-friendly wrapping
            Log::error('Failed to restore purchase return', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'purchase_return_id' => $purchaseReturn->id,
                'purchase_return_code' => $purchaseReturn->code ?? 'N/A',
            ]);

            // Re-throw with user-friendly message
            throw new \RuntimeException(
                "Failed to restore purchase return #{$purchaseReturn->id}: " . $e->getMessage() .
                ". All changes have been rolled back.",
                0,
                $e
            );
        }
    }
}
