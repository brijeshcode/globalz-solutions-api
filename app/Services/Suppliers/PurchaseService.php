<?php

namespace App\Services\Suppliers;

use App\Models\Suppliers\Purchase;
use App\Models\Suppliers\PurchaseItem;
use App\Models\Suppliers\SupplierItemPrice;
use App\Models\Inventory\ItemPrice;
use App\Models\Inventory\ItemPriceHistory;
use App\Models\Inventory\Inventory;
use App\Models\Items\Item;
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
            info('purchase update completed ');
            // Process items if provided
            if (!empty($items)) {
                $this->updatePurchaseItems($purchase, $items);
            }

            info('now going to recalculation');
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
                        'total_price_usd' => $totalPrice * $purchase->currency_rate,
                        'note' => $itemData['note'] ?? $purchaseItem->note,
                    ]);
                    
                    // Recalculate derived fields
                    info('2. re calculating purchase items fileslike totla ship, customs, other');

                    $updatedData = $this->preparePurchaseItemData($purchase, $itemData);
                    $purchaseItem->update([
                        'total_shipping_usd' => $updatedData['total_shipping_usd'],
                        'total_customs_usd' => $updatedData['total_customs_usd'],
                        'total_other_usd' => $updatedData['total_other_usd'],
                        'final_total_cost_usd' => $updatedData['final_total_cost_usd'],
                        'cost_per_item_usd' => $updatedData['cost_per_item_usd'],
                    ]);
                    
                    // Update related data if cost or quantity changed
                    if ($oldCostPerItemUsd != $purchaseItem->cost_per_item_usd || $oldQuantity != $purchaseItem->quantity) {
                        info('3 update inventory');
                        $this->processPurchaseItemRelatedData($purchase, $purchaseItem, true, $oldQuantity);
                    }
                    info('7  update inventory completed');
                    
                    $processedItemIds[] = $purchaseItem->id;
                }
            } else {
                info('updating:create new item');  
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
        $totalPriceUsd = $totalPrice * $purchase->currency_rate;
        
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
    protected function processPurchaseItemRelatedData(Purchase $purchase, PurchaseItem $purchaseItem, bool $isUpdate = false, int $oldQuantity = 0): void
    {
        // 1. Update inventory
        $this->updateInventory($purchase, $purchaseItem, $isUpdate, $oldQuantity);
        
        info('4 update suppleir item price');
        // 2. Update supplier item prices
        $this->updateSupplierItemPrice($purchase, $purchaseItem);
        
        info('5 update suppleir item price history');
        // 3. Update item price and price history
        $this->updateItemPriceAndHistory($purchase, $purchaseItem);
    }

    /**
     * Update inventory for purchase item
     */
    protected function updateInventory(Purchase $purchase, PurchaseItem $purchaseItem, bool $isUpdate = false, int $oldQuantity = 0): void
    {
        $inventory = Inventory::byWarehouseAndItem($purchase->warehouse_id, $purchaseItem->item_id)->first();
        
        if ($inventory) {
            if ($isUpdate) {
                // For updates, adjust inventory by the difference between old and new quantities
                $quantityDifference = $purchaseItem->quantity - $oldQuantity;
                $inventory->quantity += $quantityDifference;
            } else {
                // Add quantity to existing inventory (for new items)
                $inventory->quantity += $purchaseItem->quantity;
            }
            $inventory->save();
        } else {
            // Create new inventory record
            Inventory::create([
                'warehouse_id' => $purchase->warehouse_id,
                'item_id' => $purchaseItem->item_id,
                'quantity' => $purchaseItem->quantity,
            ]);
        }
    }

    /**
     * Update supplier item price
     */
    protected function updateSupplierItemPrice(Purchase $purchase, PurchaseItem $purchaseItem): void
    {
        // Get current active supplier item price
        $currentSupplierPrice = SupplierItemPrice::bySupplierAndItem($purchase->supplier_id, $purchaseItem->item_id)
            ->where('is_current', true)
            ->first();
        
        $newPrice = $purchaseItem->price;
        $newPriceUsd = $purchaseItem->price * $purchase->currency_rate;
        
        // Only create new price record if price has changed significantly or no current price exists
        $shouldCreateNewPrice = false;
        
        if (!$currentSupplierPrice) {
            info('should create new supplier price');
            // No existing price, create new one
            $shouldCreateNewPrice = true;
        } else {
            // Compare original currency price, not USD price (because USD changes with exchange rates)
            $priceDifference = abs($newPrice - $currentSupplierPrice->price);
            info('Update supplier price hsitroy');
            info('Price deference: '. $priceDifference);

            if ($priceDifference > 0) {
                $shouldCreateNewPrice = true;
            } else {
                // Price hasn't changed significantly, just update the last purchase info and USD conversion
                // $currentSupplierPrice->update([
                //     'price_usd' => $newPriceUsd,
                //     'currency_rate' => $purchase->currency_rate,
                //     'last_purchase_id' => $purchase->id,
                //     'last_purchase_date' => $purchase->date,
                // ]);
            }
        }
        
        if ($shouldCreateNewPrice) {
            // Mark existing prices as not current
            SupplierItemPrice::bySupplierAndItem($purchase->supplier_id, $purchaseItem->item_id)
                ->update(['is_current' => false]);
            
            // Create new current price record
            SupplierItemPrice::create([
                'supplier_id' => $purchase->supplier_id,
                'item_id' => $purchaseItem->item_id,
                'currency_id' => $purchase->currency_id,
                'price' => $purchaseItem->price,
                'price_usd' => $newPriceUsd,
                'currency_rate' => $purchase->currency_rate,
                'last_purchase_id' => $purchase->id,
                'last_purchase_date' => $purchase->date,
                'is_current' => true,
            ]);
        }
    }

    /**
     * Update item price and price history
     */
    protected function updateItemPriceAndHistory(Purchase $purchase, PurchaseItem $purchaseItem): void
    {
        $item = $purchaseItem->item;
        $newPriceUsd = $purchaseItem->cost_per_item_usd;
        
        // Get current item price
        $currentItemPrice = ItemPrice::byItem($purchaseItem->item_id)->first();
        
        if ($currentItemPrice) {
            $oldPriceUsd = $currentItemPrice->price_usd;
            
            // Check if item uses weighted average calculation
            if ($item->cost_calculation === Item::COST_WEIGHTED_AVERAGE) {
                $newPriceUsd = $this->calculateWeightedAveragePrice($purchaseItem, $oldPriceUsd);
            }
            
            // Only update if price changed significantly (more than 0.01 difference)
            $priceDifference = abs($newPriceUsd - $oldPriceUsd);
            
            if ($priceDifference > 0) {
                // Create price history for significant price change
                ItemPriceHistory::create([
                    'item_id' => $purchaseItem->item_id,
                    'purchase_id' => $purchase->id,
                    'price_usd' => $newPriceUsd,
                    'average_waited_price' => $newPriceUsd, // For weighted average
                    'latest_price' => $oldPriceUsd,
                    'effective_date' => $purchase->date,
                ]);
                
                // Update current item price
                $currentItemPrice->update([
                    'price_usd' => $newPriceUsd,
                    'effective_date' => $purchase->date,
                    'last_purchase_id' => $purchase->id,
                ]);
            } else {
                // Price hasn't changed significantly, just update the last purchase info
                $currentItemPrice->update([
                    'last_purchase_id' => $purchase->id,
                ]);
            }
        } else {
            // Create new item price (first time for this item)
            ItemPrice::create([
                'item_id' => $purchaseItem->item_id,
                'price_usd' => $newPriceUsd,
                'effective_date' => $purchase->date,
                'last_purchase_id' => $purchase->id,
            ]);
        }
    }

    /**
     * Calculate weighted average price for an item
     */
    protected function calculateWeightedAveragePrice(PurchaseItem $purchaseItem, float $currentPriceUsd): float
    {
        // Get current inventory quantity
        $currentInventory = Inventory::byWarehouseAndItem(
            $purchaseItem->purchase->warehouse_id,
            $purchaseItem->item_id
        )->first();
        
        $currentQuantity = $currentInventory ? $currentInventory->quantity : 0;
        $newQuantity = $purchaseItem->quantity;
        $newPriceUsd = $purchaseItem->cost_per_item_usd;
        
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
     * Handle purchase item deletion
     */
    protected function handlePurchaseItemDeletion(Purchase $purchase, PurchaseItem $purchaseItem): void
    {
        // Adjust inventory (remove quantity)
        $inventory = Inventory::byWarehouseAndItem($purchase->warehouse_id, $purchaseItem->item_id)->first();
        if ($inventory) {
            $inventory->quantity -= $purchaseItem->quantity;
            $inventory->save();
        }
        
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