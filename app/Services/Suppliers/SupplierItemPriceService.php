<?php

namespace App\Services\Suppliers;

use App\Models\Suppliers\SupplierItemPrice;
use App\Models\Suppliers\Purchase;
use App\Models\Suppliers\PurchaseItem;

class SupplierItemPriceService
{
    /**
     * Update the USD price based on currency rate
     */
    public function updatePriceUsd(SupplierItemPrice $supplierPrice, ?float $currencyRate = null): void
    {
        $rate = $currencyRate ?? $supplierPrice->currency_rate ?? 1.0;
        $supplierPrice->update(['price_usd' => $supplierPrice->price / $rate]);
    }

    /**
     * Make this supplier item price the current one for the supplier-item combination
     */
    public function makeCurrentForSupplierItem(SupplierItemPrice $supplierPrice): void
    {
        // Mark all other prices for this supplier-item combination as not current
        SupplierItemPrice::where('supplier_id', $supplierPrice->supplier_id)
            ->where('item_id', $supplierPrice->item_id)
            ->where('id', '!=', $supplierPrice->id)
            ->update(['is_current' => false]);

        // Mark this one as current
        $supplierPrice->update(['is_current' => true]);
    }

    /**
     * Update supplier price from purchase information
     */
    public function updateFromPurchase(SupplierItemPrice $supplierPrice, Purchase $purchase, PurchaseItem $purchaseItem): void
    {
        $supplierPrice->update([
            'last_purchase_id' => $purchase->id,
            'last_purchase_date' => $purchase->date,
            'currency_rate' => $purchase->currency_rate,
            'price_usd' => $purchaseItem->price / $purchase->currency_rate,
        ]);
    }

    /**
     * Get current price for supplier-item combination
     */
    public function getCurrentPriceForSupplierItem(int $supplierId, int $itemId): ?SupplierItemPrice
    {
        return SupplierItemPrice::where('supplier_id', $supplierId)
            ->where('item_id', $itemId)
            ->where('is_current', true)
            ->first();
    }

    /**
     * Get best current prices for an item across all suppliers
     */
    public function getBestCurrentPricesForItem(int $itemId, int $limit = 5): \Illuminate\Database\Eloquent\Collection
    {
        return SupplierItemPrice::with('supplier')
            ->where('item_id', $itemId)
            ->where('is_current', true)
            ->orderBy('price_usd')
            ->limit($limit)
            ->get();
    }

    /**
     * Create supplier item price from purchase item
     */
    public function createFromPurchaseItem(Purchase $purchase, PurchaseItem $purchaseItem): SupplierItemPrice
    {
        return SupplierItemPrice::create([
            'supplier_id' => $purchase->supplier_id,
            'item_id' => $purchaseItem->item_id,
            'currency_id' => $purchase->currency_id,
            'price' => $purchaseItem->price,
            'price_usd' => $purchaseItem->price / $purchase->currency_rate,
            'currency_rate' => $purchase->currency_rate,
            'last_purchase_id' => $purchase->id,
            'last_purchase_date' => $purchase->date,
            'is_current' => true,
        ]);
    }

    /**
     * Update or create supplier item price from purchase item
     */
    public function updateOrCreateFromPurchaseItem(Purchase $purchase, PurchaseItem $purchaseItem): SupplierItemPrice
    {
        $existingPrice = $this->getCurrentPriceForSupplierItem($purchase->supplier_id, $purchaseItem->item_id);

        if ($existingPrice) {
            // Check if price has changed significantly
            $newPrice = $purchaseItem->price;
            $priceDifference = abs($newPrice - $existingPrice->price);
            
            if ($priceDifference > 0.01) {
                // Price changed significantly, create new price record
                $this->makeCurrentForSupplierItem($existingPrice);
                return $this->createFromPurchaseItem($purchase, $purchaseItem);
            } else {
                // Price hasn't changed much, just update the existing record
                $this->updateFromPurchase($existingPrice, $purchase, $purchaseItem);
                return $existingPrice->fresh();
            }
        } else {
            // No existing price, create new one
            return $this->createFromPurchaseItem($purchase, $purchaseItem);
        }
    }
}