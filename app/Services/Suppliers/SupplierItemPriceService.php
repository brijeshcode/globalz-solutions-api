<?php

namespace App\Services\Suppliers;

use App\Models\Suppliers\SupplierItemPrice;
use App\Models\Suppliers\Purchase;
use App\Models\Suppliers\PurchaseItem;
use App\Models\Items\Item;
use App\Services\Currency\CurrencyService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class SupplierItemPriceService
{
    /**
     * Initialize supplier price from item's base price (for new supplier-item relationships)
     */
    public static function initializeFromItem(int $supplierId, Item $item): ?SupplierItemPrice
    {
        // Check if supplier already has a price for this item
        if (self::getCurrentPrice($supplierId, $item->id)) {
            return null; // Already exists
        }
        $usdId = CurrencyService::getUSDCurrencyId();
        // Use item's base_cost as initial supplier price
        if ($item->base_cost > 0) {
            return self::create([
                'supplier_id' => $supplierId,
                'item_id' => $item->id,
                'currency_id' => $usdId ?? 1,
                'price' => $item->base_cost,
                'price_usd' => $item->base_cost ,
                'currency_rate' => 1,
                'last_purchase_id' => null,
                'last_purchase_date' => now()->format('Y-m-d'),
                'is_current' => true,
                'note' => 'Initialized from item base cost'
            ]);
        }
        dd('stop');

        return null;
    }

    /**
     * Create a new supplier item price
     */
    public static function create(array $data): SupplierItemPrice
    {
        return DB::transaction(function () use ($data) {
            // Mark existing prices as not current
            if ($data['is_current'] ?? false) {
                self::markOthersAsNotCurrent($data['supplier_id'], $data['item_id']);
            }
            info($data);
            return SupplierItemPrice::create($data);
        });
    }

    /**
     * Mark all other prices for supplier-item combination as not current
     */
    public static function markOthersAsNotCurrent(int $supplierId, int $itemId, ?int $excludeId = null): void
    {
        $query = SupplierItemPrice::where('supplier_id', $supplierId)
            ->where('item_id', $itemId);
            
        if ($excludeId) {
            $query->where('id', '!=', $excludeId);
        }
        
        $query->update(['is_current' => false]);
    }

    /**
     * Make this supplier item price the current one
     */
    public static function makeCurrent(SupplierItemPrice $supplierPrice): void
    {
        DB::transaction(function () use ($supplierPrice) {
            self::markOthersAsNotCurrent(
                $supplierPrice->supplier_id, 
                $supplierPrice->item_id, 
                $supplierPrice->id
            );
            
            $supplierPrice->update(['is_current' => true]);
        });
    }

    /**
     * Update supplier price from purchase information
     */
    public static function updateFromPurchase(SupplierItemPrice $supplierPrice, Purchase $purchase, PurchaseItem $purchaseItem): void
    {
        $supplierPrice->update([
            'last_purchase_id' => $purchase->id,
            'last_purchase_date' => $purchase->date,
            'currency_rate' => $purchase->currency_rate,
            'price_usd' => CurrencyService::convertToBaseWithRate($purchaseItem->price, $purchase->currency_id, $purchase->currency_rate),
        ]);
    }

    /**
     * Get current price for supplier-item combination
     */
    public static function getCurrentPrice(int $supplierId, int $itemId): ?SupplierItemPrice
    {
        return SupplierItemPrice::bySupplierAndItem($supplierId, $itemId)
            ->where('is_current', true)
            ->first();
    }

    /**
     * Get best current prices for an item across all suppliers
     */
    public static function getBestPricesForItem(int $itemId, int $limit = 5): \Illuminate\Database\Eloquent\Collection
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
    public static function createFromPurchase(Purchase $purchase, PurchaseItem $purchaseItem): SupplierItemPrice
    {
        return self::create([
            'supplier_id' => $purchase->supplier_id,
            'item_id' => $purchaseItem->item_id,
            'currency_id' => $purchase->currency_id,
            'price' => $purchaseItem->price,
            'price_usd' => CurrencyService::convertToBaseWithRate($purchaseItem->price, $purchase->currency_id, $purchase->currency_rate),
            'currency_rate' => $purchase->currency_rate,
            'last_purchase_id' => $purchase->id,
            'last_purchase_date' => $purchase->date,
            'is_current' => true,
        ]);
    }

    /**
     * Update or create supplier item price from purchase
     */
    public static function updateOrCreateFromPurchase(Purchase $purchase, PurchaseItem $purchaseItem): SupplierItemPrice
    {
        $existingPrice = self::getCurrentPrice($purchase->supplier_id, $purchaseItem->item_id);

        if ($existingPrice) {
            // Check if price has changed significantly
            $newPrice = $purchaseItem->price;
            $priceDifference = abs($newPrice - $existingPrice->price);
            
            if ($priceDifference > 0) {
                // Price changed, mark old as not current and create new price record
                self::markOthersAsNotCurrent($purchase->supplier_id, $purchaseItem->item_id);
                return self::createFromPurchase($purchase, $purchaseItem);
            } else {
                // Price hasn't changed, just update the existing record
                self::updateFromPurchase($existingPrice, $purchase, $purchaseItem);
                return $existingPrice->fresh();
            }
        } else {
            // No existing price, create new one
            return self::createFromPurchase($purchase, $purchaseItem);
        }
    }

    /**
     * Update the USD price based on currency rate
     */
    public static function updatePriceUsd(SupplierItemPrice $supplierPrice, ?float $currencyRate = null): void
    {
        $rate = $currencyRate ?? $supplierPrice->currency_rate ?? 1.0;
        $priceUsd = CurrencyService::convertToBaseWithRate($supplierPrice->price, $supplierPrice->currency_id, $rate);
        $supplierPrice->update(['price_usd' => $priceUsd]);
    }

    /**
     * Get price history for supplier-item combination
     */
    public static function getPriceHistory(int $supplierId, int $itemId, int $limit = 50): \Illuminate\Database\Eloquent\Collection
    {
        return SupplierItemPrice::bySupplierAndItem($supplierId, $itemId)
            ->orderBy('last_purchase_date', 'desc')
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    /**
     * Get all current prices for a supplier
     */
    public static function getSupplierPrices(int $supplierId): \Illuminate\Database\Eloquent\Collection
    {
        return SupplierItemPrice::with(['item'])
            ->where('supplier_id', $supplierId)
            ->where('is_current', true)
            ->orderBy('price_usd')
            ->get();
    }

    /**
     * Initialize missing supplier prices from item base costs
     */
    public static function initializeMissingPrices(int $supplierId, ?int $currencyId = null, ?float $currencyRate = null): array
    {
        $results = [];
        $currencyId = $currencyId ?? 1; // Default currency
        $currencyRate = $currencyRate ?? 1.0; // Default rate

        // Get items that have base_cost but no current supplier price
        $itemsWithoutPrices = Item::whereNotNull('base_cost')
            ->where('base_cost', '>', 0)
            ->whereDoesntHave('supplierItemPrices', function ($query) use ($supplierId) {
                $query->where('supplier_id', $supplierId)
                      ->where('is_current', true);
            })
            ->get();

        foreach ($itemsWithoutPrices as $item) {
            try {
                $supplierPrice = self::initializeFromItem($supplierId, $item, $currencyId, $currencyRate);
                if ($supplierPrice) {
                    $results[] = [
                        'item_id' => $item->id,
                        'item_code' => $item->code,
                        'price' => $item->base_cost,
                        'status' => 'initialized'
                    ];
                }
            } catch (\Exception $e) {
                $results[] = [
                    'item_id' => $item->id,
                    'item_code' => $item->code,
                    'price' => $item->base_cost,
                    'status' => 'failed',
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }
}