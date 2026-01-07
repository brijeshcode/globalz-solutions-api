<?php

namespace App\Services\Suppliers;

use App\Helpers\CurrencyHelper;
use App\Models\Suppliers\Purchase;
use App\Models\Suppliers\PurchaseItem;
use App\Models\Items\Item;
use App\Services\Inventory\InventoryService;
use App\Services\Inventory\PriceService;
use App\Services\Suppliers\SupplierItemPriceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PurchaseService
{
    /**
     * Create a new purchase with items and all related data
     */
    public function createPurchaseWithItems(array $purchaseData, array $items = []): Purchase
    {
        try {
            return DB::transaction(function () use ($purchaseData, $items) {
                // Create the purchase
                $purchase = Purchase::create($purchaseData);

                // Process purchase items
                if (!empty($items)) {
                    $this->processPurchaseItems($purchase, $items);
                }

                // Recalculate purchase totals
                $this->recalculatePurchaseTotals($purchase);

                return $purchase->fresh();
            });
        } catch (\InvalidArgumentException $e) {
            // Validation errors are already user-friendly, just log and re-throw
            Log::warning('Purchase creation validation failed', [
                'error' => $e->getMessage(),
                'purchase_data' => $purchaseData,
                'items_count' => count($items),
            ]);

            // Re-throw validation errors as-is (already have clear messages)
            throw $e;
        } catch (\Exception $e) {
            // System/unexpected errors need context and user-friendly wrapping
            Log::error('Failed to create purchase with items', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'purchase_data' => $purchaseData,
                'items_count' => count($items),
            ]);

            // Re-throw with user-friendly message
            throw new \RuntimeException(
                "Failed to create purchase: " . $e->getMessage() .
                ". All changes have been rolled back.",
                0,
                $e
            );
        }
    }

    /**
     * Update purchase with items
     */
    public function updatePurchaseWithItems(Purchase $purchase, array $purchaseData, array $items = []): Purchase
    {
        try {
            return DB::transaction(function () use ($purchase, $purchaseData, $items) {
                // Update purchase data (exclude items from purchaseData)


                $purchase->update($purchaseData);

                $this->updatePurchaseItems($purchase, $items);

                // Recalculate purchase totals
                $this->recalculatePurchaseTotals($purchase);

                return $purchase->fresh();
            });
        } catch (\InvalidArgumentException $e) {
            // Validation errors are already user-friendly, just log and re-throw
            Log::warning('Purchase update validation failed', [
                'error' => $e->getMessage(),
                'purchase_id' => $purchase->id,
                'purchase_code' => $purchase->code ?? 'N/A',
                'items_count' => count($items),
            ]);

            // Re-throw validation errors as-is (already have clear messages)
            throw $e;
        } catch (\Exception $e) {
            // System/unexpected errors need context and user-friendly wrapping
            Log::error('Failed to update purchase with items', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'purchase_id' => $purchase->id,
                'purchase_code' => $purchase->code ?? 'N/A',
                'items_count' => count($items),
            ]);

            // Re-throw with user-friendly message
            throw new \RuntimeException(
                "Failed to update purchase #{$purchase->id}: " . $e->getMessage() .
                ". All changes have been rolled back.",
                0,
                $e
            );
        }
    }

    /**
     * Process purchase items for creation
     */
    private function processPurchaseItems(Purchase $purchase, array $items): void
    {
        foreach ($items as $itemData) {
            $purchaseItem = $this->createPurchaseItem($purchase, $itemData);
            
            // Process all related updates
            $this->processPurchaseItemRelatedData($purchase, $purchaseItem);
        }
    }

    /**
     * Update purchase items individually
     */
    private function updatePurchaseItems(Purchase $purchase, array $items): void
    {
        $processedItemIds = [];
        foreach ($items as $itemData) {
            if (isset($itemData['id'])) {
                // Update existing item
                $purchaseItem = PurchaseItem::where('id', $itemData['id'])
                    ->where('purchase_id', $purchase->id)
                    ->first();
                
                if ($purchaseItem) {
                    
                    $oldCostPerItemUsd = $purchaseItem->cost_per_item_usd;
                    $oldQuantity = $purchaseItem->quantity;
                    
                    // Update only the fields that can change, preserve timestamps
                    $price = $itemData['price'];
                    $quantity = $itemData['quantity'];
                    $discountAmount = $itemData['discount_amount'] ?? 0; // This is total line discount
                    $discountPercent = $itemData['discount_percent'] ?? 0;
                    
                    // Calculate discount amount if discount percent is provided
                    if ($discountPercent > 0) {
                        $discountAmount = ($price * $quantity * $discountPercent) / 100;
                    }
                    
                    $totalBeforeDiscount = $price * $quantity;
                    $totalPrice = $totalBeforeDiscount - $discountAmount;
                    
                    $purchaseItem->update([
                        'price' => $price,
                        'quantity' => $quantity,
                        'discount_percent' => $discountPercent,
                        'discount_amount' => $discountAmount, // Store total line discount
                        'total_price' => $totalPrice,
                        'total_price_usd' => CurrencyHelper::toUsd($purchase->currency_id, $totalPrice, $purchase->currency_rate),
                        'note' => $itemData['note'] ?? $purchaseItem->note,
                    ]);

                    // Refresh purchase to get updated totals from the saved event
                    $purchase->refresh();

                    // Recalculate derived fields
                    $updatedData = $this->preparePurchaseItemData($purchase, $itemData);
                    $purchaseItem->update([
                        'total_shipping_usd' => $updatedData['total_shipping_usd'],
                        'total_customs_usd' => $updatedData['total_customs_usd'],
                        'total_other_usd' => $updatedData['total_other_usd'],
                        'final_total_cost_usd' => $updatedData['final_total_cost_usd'],
                        'cost_per_item_usd' => $updatedData['cost_per_item_usd'],
                    ]);
                    
                    // Update related data if cost or quantity changed
                    if ($oldCostPerItemUsd != $purchaseItem->cost_per_item_usd || (float)$oldQuantity != (float)$purchaseItem->quantity) {
                        $this->processPurchaseItemRelatedData($purchase, $purchaseItem, true, (float)$oldQuantity, $oldCostPerItemUsd);
                    }
                    $processedItemIds[] = $purchaseItem->id;
                }
            } else {
                // Create new item (only if no ID provided)
                $purchaseItem = $this->createPurchaseItem($purchase, $itemData);
                $this->processPurchaseItemRelatedData($purchase, $purchaseItem);
                $processedItemIds[] = $purchaseItem->id;
            }
        }
        
        // Handle removed items - items that exist in DB but not in the update list
        $removedItems = $purchase->purchaseItems()
            ->whereNotIn('id', $processedItemIds)
            ->get();
        foreach ($removedItems as $removedItem) {
            $this->handlePurchaseItemDeletion($purchase, $removedItem);
        }
        
        // Delete the removed items from database
        $purchase->purchaseItems()
            ->whereNotIn('id', $processedItemIds)
            ->delete();
    }

    /**
     * Create a single purchase item
     */
    private function createPurchaseItem(Purchase $purchase, array $itemData): PurchaseItem
    {
        $preparedData = $this->preparePurchaseItemData($purchase, $itemData);
        return PurchaseItem::create($preparedData);
    }

    /**
     * Prepare purchase item data with calculations
     */
    private function preparePurchaseItemData(Purchase $purchase, array $itemData): array
    {
        $item = Item::findOrFail($itemData['item_id']);
        
        // Calculate item totals
        $price = $itemData['price'];
        $quantity = $itemData['quantity'];
        $discountPercent = $itemData['discount_percent'] ?? 0;
        $discountAmount = $itemData['discount_amount'] ?? 0; // This is total line discount
        
        // Calculate discount amount if discount percent is provided
        if ($discountPercent > 0) {
            $discountAmount = ($price * $quantity * $discountPercent) / 100;
        }
        
        $totalBeforeDiscount = $price * $quantity;
        $totalPrice = $totalBeforeDiscount - $discountAmount;
        $totalPriceUsd = CurrencyHelper::toUsd($purchase->currency_id, $totalPrice, $purchase->currency_rate) ;
        
        // Calculate proportional fees based on purchase totals
        $totalShippingUsd = $this->calculateProportionalFee($totalPriceUsd, $purchase, 'shipping_fee_usd', 'shipping_fee_usd_percent');
        $totalCustomsUsd = $this->calculateProportionalFee($totalPriceUsd, $purchase, 'customs_fee_usd', 'customs_fee_usd_percent');
        $totalOtherUsd = $this->calculateProportionalFee($totalPriceUsd, $purchase, 'other_fee_usd', 'other_fee_usd_percent');
        
        $finalTotalCostUsd = $totalPriceUsd + $totalShippingUsd + $totalCustomsUsd + $totalOtherUsd;
        $costPerItemUsd = $quantity > 0 ? ($finalTotalCostUsd / $quantity) : 0;
        
        return [
            'purchase_id' => $purchase->id,
            'item_id' => $itemData['item_id'],
            'item_code' => $item->code,
            'price' => $price,
            'quantity' => $quantity,
            'discount_percent' => $discountPercent,
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
     * Calculate proportional fees for purchase item
     */
    private function calculateProportionalFee(float $itemTotalUsd, Purchase $purchase, string $feeField, string $percentField): float
    {
        // If percentage is set, calculate based on item total
        if ($purchase->{$percentField} > 0) {
            return ($itemTotalUsd * $purchase->{$percentField}) / 100;
        }
        
        // If fixed amount is set, calculate proportional share
        if ($purchase->{$feeField} > 0) {
            $totalPurchaseUsd = $purchase->sub_total_usd > 0 ? $purchase->sub_total_usd : 1;
            return ($purchase->{$feeField} * $itemTotalUsd) / $totalPurchaseUsd;
        }
        
        return 0;
    }

    /**
     * Process all related data for a purchase item
     * Only processes if purchase status is 'Delivered'
     */
    private function processPurchaseItemRelatedData(Purchase $purchase, PurchaseItem $purchaseItem, bool $isUpdate = false, int $oldQuantity = 0, ?float $oldCostPerItemUsd = null): void
    {
        // Only update prices, supplier prices, and inventory if purchase is Delivered
        if ($purchase->status === 'Delivered') {
            // 1. Update item price and price history (BEFORE updating inventory)
            PriceService::updateFromPurchase($purchase, $purchaseItem, $isUpdate, $oldCostPerItemUsd, $oldQuantity);

            // 2. Update supplier item prices
            SupplierItemPriceService::updateOrCreateFromPurchase($purchase, $purchaseItem);

            // 3. Update inventory (AFTER price calculation)
            $this->updateInventory($purchase, $purchaseItem, $isUpdate, $oldQuantity);
        }
    }

    /**
     * Validate that reducing purchase quantity won't cause negative inventory
     */
    private function validateInventoryBeforeReduction(int $itemId, int $warehouseId, int $reductionAmount, int $oldPurchaseQty, int $newPurchaseQty): void
    {
        $currentInventory = InventoryService::getQuantity($itemId, $warehouseId);
        $inventoryAfterReduction = $currentInventory - $reductionAmount;

        if ($inventoryAfterReduction < 0) {
            $item = Item::find($itemId);
            $itemName = $item ? $item->name : "Item #{$itemId}";

            // Calculate how many units have already been sold/used from this purchase
            $soldFromThisPurchase = $oldPurchaseQty - $currentInventory;

            // Maximum quantity we can reduce to
            $maxAllowedReduction = $currentInventory;
            $minAllowedNewQuantity = $oldPurchaseQty - $maxAllowedReduction;

            throw new \InvalidArgumentException(
                "Cannot reduce purchase quantity for '{$itemName}'. " .
                "Original purchase: {$oldPurchaseQty} units. " .
                "Current inventory: {$currentInventory} units ({$soldFromThisPurchase} already sold/used). " .
                "You tried to reduce to: {$newPurchaseQty} units (reduction of {$reductionAmount}). " .
                "Minimum allowed: {$minAllowedNewQuantity} units (can reduce by maximum {$maxAllowedReduction} units)."
            );
        }
    }

    /**
     * Validate that removing a purchase item won't cause negative inventory
     *
     * @throws \InvalidArgumentException if removal would cause negative inventory
     */
    private function validateInventoryBeforeDeletion(int $itemId, int $warehouseId, int $purchaseQuantity): void
    {
        $currentInventory = InventoryService::getQuantity($itemId, $warehouseId);

        if ($currentInventory < $purchaseQuantity) {
            $item = Item::find($itemId);
            $itemName = $item ? $item->name : "Item #{$itemId}";

            // Calculate how many units have already been sold/used from this purchase
            $soldFromThisPurchase = $purchaseQuantity - $currentInventory;

            throw new \InvalidArgumentException(
                "Cannot remove '{$itemName}' from this purchase. " .
                "Purchased quantity: {$purchaseQuantity} units. " .
                "Current inventory: {$currentInventory} units ({$soldFromThisPurchase} already sold/used). " .
                "You cannot remove an item that has already been partially or fully sold/used. " .
                "Please adjust quantities instead of removing the item completely."
            );
        }
    }

    /**
     * Update inventory for purchase item
     */
    private function updateInventory(Purchase $purchase, PurchaseItem $purchaseItem, bool $isUpdate = false, int $oldQuantity = 0): void
    {

        if ($isUpdate) {
            // For updates, adjust inventory by the difference between old and new quantities
            $quantityDifference = $purchaseItem->quantity - $oldQuantity;
            if ($quantityDifference != 0) {
                // Validate: Reducing purchase quantity shouldn't cause negative inventory
                if ($quantityDifference < 0) {
                    $this->validateInventoryBeforeReduction(
                        $purchaseItem->item_id,
                        $purchase->warehouse_id,
                        abs($quantityDifference),
                        $oldQuantity,
                        $purchaseItem->quantity
                    );
                }

                InventoryService::adjust(
                    $purchaseItem->item_id,
                    $purchase->warehouse_id,
                    $quantityDifference
                );
            }
        } else {
            // Add quantity to inventory (for new items)
            InventoryService::add(
                $purchaseItem->item_id,
                $purchase->warehouse_id,
                $purchaseItem->quantity
            );
        }
    }

    /**
     * Handle purchase item deletion
     */
    private function handlePurchaseItemDeletion(Purchase $purchase, PurchaseItem $purchaseItem): void
    {
        // Only adjust inventory if purchase is already delivered
        if ($purchase->status === 'Delivered') {
            // Validate: Removing item shouldn't cause negative inventory
            $this->validateInventoryBeforeDeletion(
                $purchaseItem->item_id,
                $purchase->warehouse_id,
                $purchaseItem->quantity
            );

            // Adjust inventory (remove quantity)
            InventoryService::subtract(
                $purchaseItem->item_id,
                $purchase->warehouse_id,
                $purchaseItem->quantity
            );
        }

        // Note: We don't remove supplier prices or item price history as they are historical records
    }

    /**
     * Recalculate purchase totals from items
     */
    private function recalculatePurchaseTotals(Purchase $purchase): void
    {
        $items = $purchase->purchaseItems;

        $subTotal = $items->sum('total_price');
        $subTotalUsd = $items->sum('total_price_usd');
        $total = $subTotal - $purchase->discount_amount + CurrencyHelper::fromUsd($purchase->currency_id, $purchase->tax_usd);
        $totalUsd = $subTotalUsd - $purchase->discount_amount_usd + $purchase->tax_usd;

        
        // final total usd = total + custom + shipping + other fees 
        $finalTotalUsd = $totalUsd + $purchase->shipping_fee_usd + $purchase->customs_fee_usd
        + $purchase->other_fee_usd + $purchase->tax_usd;

        // final total = UsdToCurrency(final total usd ) 
        
        $finalTotal = CurrencyHelper::fromUsd($purchase->currency_id, $total) ;

        $purchase->update([
            'sub_total' => $subTotal,
            'sub_total_usd' => $subTotalUsd,
            'total' => $total,
            'total_usd' => $totalUsd,
            'final_total' => $finalTotal,
            'final_total_usd' => $finalTotalUsd,
        ]);
    }

    /**
     * Delete a purchase and adjust inventory
     */
    public function deletePurchase(Purchase $purchase): void
    {
        try {
            DB::transaction(function () use ($purchase) {
                // Load purchase items before deletion
                $purchaseItems = $purchase->purchaseItems;

                // Only adjust inventory if purchase is delivered
                if ($purchase->status === 'Delivered') {
                    // Validate that all items can be removed from inventory
                    foreach ($purchaseItems as $purchaseItem) {
                        $this->validateInventoryBeforeDeletion(
                            $purchaseItem->item_id,
                            $purchase->warehouse_id,
                            $purchaseItem->quantity
                        );
                    }

                    // Subtract inventory and clean up price history for all items
                    foreach ($purchaseItems as $purchaseItem) {
                        // Subtract inventory
                        InventoryService::subtract(
                            $purchaseItem->item_id,
                            $purchase->warehouse_id,
                            $purchaseItem->quantity
                        );

                        // Clean up price history and restore previous price
                        PriceService::deleteFromPurchase($purchase, $purchaseItem);
                    }
                }

                // Delete all purchase items
                $purchase->purchaseItems()->delete();

                // Delete the purchase (this will trigger model events for supplier balance)
                $purchase->delete();
            });
        } catch (\InvalidArgumentException $e) {
            // Validation errors are already user-friendly, just log and re-throw
            Log::warning('Purchase deletion validation failed', [
                'error' => $e->getMessage(),
                'purchase_id' => $purchase->id,
                'purchase_code' => $purchase->code ?? 'N/A',
            ]);

            // Re-throw validation errors as-is (already have clear messages)
            throw $e;
        } catch (\Exception $e) {
            // System/unexpected errors need context and user-friendly wrapping
            Log::error('Failed to delete purchase', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'purchase_id' => $purchase->id,
                'purchase_code' => $purchase->code ?? 'N/A',
            ]);

            // Re-throw with user-friendly message
            throw new \RuntimeException(
                "Failed to delete purchase #{$purchase->id}: " . $e->getMessage() .
                ". All changes have been rolled back.",
                0,
                $e
            );
        }
    }

    /**
     * Deliver a purchase and add inventory
     * Called when purchase status changes to 'Delivered'
     */
    public function deliverPurchase(Purchase $purchase): void
    {
        try {
            DB::transaction(function () use ($purchase) {
                // Validate that purchase is not already delivered
                if ($purchase->status === 'Delivered') {
                    throw new \InvalidArgumentException(
                        "Purchase #{$purchase->id} is already delivered. Inventory has already been added."
                    );
                }

                // Update status to Delivered
                $purchase->update(['status' => 'Delivered']);

                // Load purchase items
                $purchaseItems = $purchase->purchaseItems;

                if ($purchaseItems->isEmpty()) {
                    throw new \InvalidArgumentException(
                        "Cannot deliver purchase #{$purchase->id}. No items found in this purchase."
                    );
                }

                foreach ($purchaseItems as $purchaseItem) {
                    // 1. Update item price and price history
                    PriceService::updateFromPurchase($purchase, $purchaseItem, false, null, 0);

                    // 2. Update supplier item prices
                    SupplierItemPriceService::updateOrCreateFromPurchase($purchase, $purchaseItem);

                    // 3. Add inventory
                    InventoryService::add(
                        $purchaseItem->item_id,
                        $purchase->warehouse_id,
                        $purchaseItem->quantity
                    );
                }
            });
        } catch (\InvalidArgumentException $e) {
            // Validation errors are already user-friendly, just log and re-throw
            Log::warning('Purchase delivery validation failed', [
                'error' => $e->getMessage(),
                'purchase_id' => $purchase->id,
                'purchase_code' => $purchase->code ?? 'N/A',
            ]);

            // Re-throw validation errors as-is (already have clear messages)
            throw $e;
        } catch (\Exception $e) {
            // System/unexpected errors need context and user-friendly wrapping
            Log::error('Failed to deliver purchase', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'purchase_id' => $purchase->id,
                'purchase_code' => $purchase->code ?? 'N/A',
            ]);

            // Re-throw with user-friendly message
            throw new \RuntimeException(
                "Failed to deliver purchase #{$purchase->id}: " . $e->getMessage() .
                ". All changes have been rolled back.",
                0,
                $e
            );
        }
    }
}