<?php

namespace App\Services\Suppliers;

use App\Helpers\CurrencyHelper;
use App\Models\Suppliers\Purchase;
use App\Models\Suppliers\PurchaseItem;
use App\Models\Suppliers\SupplierItemPrice;
use App\Models\Inventory\ItemPrice;
use App\Models\Inventory\ItemPriceHistory;
use App\Models\Inventory\Inventory;
use App\Models\Items\Item;
use App\Services\Inventory\InventoryService;
use App\Services\Inventory\PriceService;
use App\Services\Suppliers\SupplierItemPriceService;
use Illuminate\Support\Facades\DB;

class PurchaseService
{
    /**
     * Create a new purchase with items and all related data
     */
    public function createPurchaseWithItems(array $purchaseData, array $items = []): Purchase
    {
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
    }

    /**
     * Update purchase with items
     */
    public function updatePurchaseWithItems(Purchase $purchase, array $purchaseData, array $items = []): Purchase
    {
        return DB::transaction(function () use ($purchase, $purchaseData, $items) {
            // Update purchase data (exclude items from purchaseData)
          
            
            $purchase->update($purchaseData);

            $this->updatePurchaseItems($purchase, $items);

            // Recalculate purchase totals
            $this->recalculatePurchaseTotals($purchase);
            
            return $purchase->fresh();
        });
    }

    /**
     * Process purchase items for creation
     */
    protected function processPurchaseItems(Purchase $purchase, array $items): void
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
    protected function updatePurchaseItems(Purchase $purchase, array $items): void
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
    protected function createPurchaseItem(Purchase $purchase, array $itemData): PurchaseItem
    {
        $preparedData = $this->preparePurchaseItemData($purchase, $itemData);
        return PurchaseItem::create($preparedData);
    }

    /**
     * Prepare purchase item data with calculations
     */
    protected function preparePurchaseItemData(Purchase $purchase, array $itemData): array
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
    protected function calculateProportionalFee(float $itemTotalUsd, Purchase $purchase, string $feeField, string $percentField): float
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
     */
    protected function processPurchaseItemRelatedData(Purchase $purchase, PurchaseItem $purchaseItem, bool $isUpdate = false, int $oldQuantity = 0, ?float $oldCostPerItemUsd = null): void
    {
        // 1. Update inventory
        $this->updateInventory($purchase, $purchaseItem, $isUpdate, $oldQuantity);

        // 2. Update supplier item prices
        SupplierItemPriceService::updateOrCreateFromPurchase($purchase, $purchaseItem);

        // 3. Update item price and price history
        PriceService::updateFromPurchase($purchase, $purchaseItem, $isUpdate, $oldCostPerItemUsd, $oldQuantity);
    }

    /**
     * Update inventory for purchase item
     */
    protected function updateInventory(Purchase $purchase, PurchaseItem $purchaseItem, bool $isUpdate = false, int $oldQuantity = 0): void
    {
        
        if ($isUpdate) {
            // For updates, adjust inventory by the difference between old and new quantities
            $quantityDifference = $purchaseItem->quantity - $oldQuantity;
            if ($quantityDifference != 0) {
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
    protected function handlePurchaseItemDeletion(Purchase $purchase, PurchaseItem $purchaseItem): void
    {
        // Adjust inventory (remove quantity)
        InventoryService::subtract(
            $purchaseItem->item_id,
            $purchase->warehouse_id,
            $purchaseItem->quantity
        );
        
        // Note: We don't remove supplier prices or item price history as they are historical records
    }

    /**
     * Recalculate purchase totals from items
     */
    protected function recalculatePurchaseTotals(Purchase $purchase): void
    {
        $items = $purchase->purchaseItems;
        
        $subTotal = $items->sum('total_price');
        $subTotalUsd = $items->sum('total_price_usd');
        $total = $subTotal - $purchase->discount_amount;
        $totalUsd = $subTotalUsd - $purchase->discount_amount_usd;
        
        $finalTotal = $total;
        $finalTotalUsd = $totalUsd + $purchase->shipping_fee_usd + $purchase->customs_fee_usd 
                        + $purchase->other_fee_usd + $purchase->tax_usd;
        
        $purchase->update([
            'sub_total' => $subTotal,
            'sub_total_usd' => $subTotalUsd,
            'total' => $total,
            'total_usd' => $totalUsd,
            'final_total' => $finalTotal,
            'final_total_usd' => $finalTotalUsd,
        ]);
    }
}