<?php

namespace App\Services\Suppliers;

use App\Helpers\CurrencyHelper;
use App\Models\Suppliers\Purchase;
use App\Models\Suppliers\PurchaseExpense;
use App\Models\Suppliers\PurchaseItem;
use App\Models\Items\Item;
use App\Services\Inventory\InventoryService;
use App\Services\Inventory\PriceService;
use App\Services\Suppliers\PurchaseExpenseService;
use App\Services\Suppliers\SupplierItemPriceService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class PurchaseService
{
    public function __construct(
        private PurchaseExpenseService $purchaseExpenseService = new PurchaseExpenseService()
    ) {}

    /**
     * Create a new purchase with items and all related data
     */
    public function createPurchaseWithItems(array $purchaseData, array $items = [], array $expenses = []): Purchase
    {
        try {
            return DB::transaction(function () use ($purchaseData, $items, $expenses) {
                $purchase = Purchase::create($purchaseData);

                // Step 1: Save expense records against the purchase
                $this->purchaseExpenseService->syncExpenseLines($purchase, $expenses);

                // Step 2: Create items with expense share baked in from the start
                $expenseShares = $this->purchaseExpenseService->computeExpenseShares($purchase, $items);
                foreach ($items as $index => $itemData) {
                    $this->createPurchaseItem($purchase, $itemData, $expenseShares[$index] ?? 0.0);
                }

                // Step 3: Update purchase-level aggregate totals
                $this->recalculatePurchaseTotals($purchase);

                // Step 4: If delivered, update cost price history and add inventory
                if ($purchase->status === 'Delivered') {
                    foreach ($purchase->purchaseItems()->get() as $purchaseItem) {
                        PriceService::updateFromPurchase($purchase, $purchaseItem, false, null, 0);
                        SupplierItemPriceService::updateOrCreateFromPurchase($purchase, $purchaseItem);
                        InventoryService::add($purchaseItem->item_id, $purchase->warehouse_id, $purchaseItem->quantity);
                    }
                }

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
    public function updatePurchaseWithItems(Purchase $purchase, array $purchaseData, array $items = [], array $expenses = []): Purchase
    {
        // Pre-validate inventory for items that will be removed, BEFORE acquiring any transaction lock.
        // This lets us fail fast with a clear error without holding the purchases row lock.
        if ($purchase->status === 'Delivered') {
            $incomingIds = collect($items)->pluck('id')->filter()->values()->toArray();
            $toBeRemoved = $purchase->purchaseItems()
                ->when(!empty($incomingIds), fn($q) => $q->whereNotIn('id', $incomingIds))
                ->get();

            foreach ($toBeRemoved as $item) {
                $this->validateInventoryBeforeDeletion($item->item_id, $purchase->warehouse_id, $item->quantity);
            }
        }

        $removedItems = collect();
        $createdItems = collect();

        try {
            // Short transaction: only data record updates (purchases + purchase_items).
            // Inventory and price side-effects are handled AFTER this transaction commits
            // so the purchases row lock is released quickly.
            $updatedPurchase = DB::transaction(function () use ($purchase, $purchaseData, $items, $expenses, &$removedItems, &$createdItems) {
                $purchase->update($purchaseData);

                // Capture original costs BEFORE any changes (keyed by purchase_item.id)
                $originalCosts = $purchase->purchaseItems()
                    ->get(['id', 'cost_per_item_usd', 'quantity'])
                    ->keyBy('id');

                // Sync expenses first so shares can be computed before items are written
                $this->purchaseExpenseService->syncExpenseLines($purchase, $expenses);
                $expenseShares = $this->purchaseExpenseService->computeExpenseShares($purchase, $items);

                $result = $this->updatePurchaseItems($purchase, $items, $expenseShares);
                $removedItems = collect($result['removed']);
                $createdItems = collect($result['created']);

                $this->recalculatePurchaseTotals($purchase);

                // Write price history once per item using the final cost
                if ($purchase->status === 'Delivered') {
                    foreach ($purchase->purchaseItems()->get() as $purchaseItem) {
                        $original        = $originalCosts->get($purchaseItem->id);
                        $isNew           = $original === null;
                        $oldCost         = $original ? (float) $original->cost_per_item_usd : null;
                        $oldQty          = $original ? (int) $original->quantity : 0;
                        $costChanged     = $oldCost !== null && abs((float) $purchaseItem->cost_per_item_usd - $oldCost) > 0.000001;
                        $qtyChanged      = $original !== null && (int) $purchaseItem->quantity !== $oldQty;

                        if ($isNew || $costChanged) {
                            PriceService::updateFromPurchase($purchase, $purchaseItem, !$isNew, $oldCost, $oldQty);
                            SupplierItemPriceService::updateOrCreateFromPurchase($purchase, $purchaseItem);
                        } elseif ($qtyChanged) {
                            PriceService::recalculateCurrentPrice($purchaseItem->item_id);
                        }
                    }
                }

                return $purchase->fresh();
            });

            // Post-transaction: adjust inventory for all changes so price calculations
            // inside the transaction used the correct pre-change inventory numbers.
            if ($updatedPurchase->status === 'Delivered') {
                // Add inventory for newly created items first (so subtract validation below
                // sees the correct stock level if the same item was removed and re-added).
                foreach ($createdItems as $createdItem) {
                    InventoryService::add($createdItem->item_id, $updatedPurchase->warehouse_id, $createdItem->quantity);
                }

                foreach ($removedItems as $removedItem) {
                    InventoryService::subtract($removedItem->item_id, $updatedPurchase->warehouse_id, $removedItem->quantity);
                    PriceService::deleteFromPurchase($updatedPurchase, $removedItem);
                    SupplierItemPriceService::deleteFromPurchase($updatedPurchase, $removedItem);
                }
            }

            return $updatedPurchase;
        } catch (\InvalidArgumentException $e) {
            Log::warning('Purchase update validation failed', [
                'error' => $e->getMessage(),
                'purchase_id' => $purchase->id,
                'purchase_code' => $purchase->code ?? 'N/A',
                'items_count' => count($items),
            ]);
            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to update purchase with items', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'purchase_id' => $purchase->id,
                'purchase_code' => $purchase->code ?? 'N/A',
                'items_count' => count($items),
            ]);
            throw new \RuntimeException(
                "Failed to update purchase #{$purchase->id}: " . $e->getMessage() .
                ". All changes have been rolled back.",
                0,
                $e
            );
        }
    }

    private function updatePurchaseItems(Purchase $purchase, array $items, array $expenseShares): array
    {
        $processedItemIds = [];
        $createdItems     = [];

        foreach ($items as $index => $itemData) {
            $expenseShare = $expenseShares[$index] ?? 0.0;

            if (isset($itemData['id'])) {
                // Update existing item
                $purchaseItem = PurchaseItem::where('id', $itemData['id'])
                    ->where('purchase_id', $purchase->id)
                    ->first();

                if ($purchaseItem) {
                    $oldQuantity = $purchaseItem->quantity;

                    $price           = $itemData['price'];
                    $quantity        = $itemData['quantity'];
                    $discountAmount  = $itemData['discount_amount'] ?? 0;
                    $discountPercent = $itemData['discount_percent'] ?? 0;

                    if ($discountPercent > 0) {
                        $discountAmount = ($price * $quantity * $discountPercent) / 100;
                    }

                    $totalPrice        = ($price * $quantity) - $discountAmount;
                    $totalPriceUsd     = CurrencyHelper::toUsd($purchase->currency_id, $totalPrice, $purchase->currency_rate);
                    $finalTotalCostUsd = $totalPriceUsd + $expenseShare;
                    $costPerItemUsd    = $quantity > 0 ? ($finalTotalCostUsd / $quantity) : 0;

                    $purchaseItem->update([
                        'price'                => $price,
                        'quantity'             => $quantity,
                        'discount_percent'     => $discountPercent,
                        'discount_amount'      => $discountAmount,
                        'total_price'          => $totalPrice,
                        'total_price_usd'      => $totalPriceUsd,
                        'total_expense_usd'    => $expenseShare,
                        'final_total_cost_usd' => $finalTotalCostUsd,
                        'cost_per_item_usd'    => $costPerItemUsd,
                        'note'                 => $itemData['note'] ?? $purchaseItem->note,
                    ]);

                    $purchase->refresh();

                    // Adjust inventory for quantity changes on existing delivered items.
                    // New items and removed items are handled post-transaction by the caller.
                    if ($purchase->status === 'Delivered' && (int) $purchaseItem->quantity !== (int) $oldQuantity) {
                        $diff = $purchaseItem->quantity - $oldQuantity;
                        if ($diff < 0) {
                            $this->validateInventoryBeforeReduction(
                                $purchaseItem->item_id,
                                $purchase->warehouse_id,
                                abs($diff),
                                $oldQuantity,
                                $purchaseItem->quantity
                            );
                        }
                        InventoryService::adjust($purchaseItem->item_id, $purchase->warehouse_id, $diff);
                    }

                    $processedItemIds[] = $purchaseItem->id;
                }
            } else {
                // Create new item — inventory will be added post-transaction by the caller
                $purchaseItem       = $this->createPurchaseItem($purchase, $itemData, $expenseShare);
                $createdItems[]     = $purchaseItem;
                $processedItemIds[] = $purchaseItem->id;
            }
        }

        // Collect removed items BEFORE soft-deleting so the caller can handle
        // inventory and price side-effects after the transaction commits.
        $removedItems = $purchase->purchaseItems()
            ->whereNotIn('id', $processedItemIds)
            ->get()
            ->all();

        $purchase->purchaseItems()
            ->whereNotIn('id', $processedItemIds)
            ->delete();

        return ['removed' => $removedItems, 'created' => $createdItems];
    }

    private function createPurchaseItem(Purchase $purchase, array $itemData, float $expenseShare = 0.0): PurchaseItem
    {
        $preparedData = $this->preparePurchaseItemData($purchase, $itemData, $expenseShare);
        return PurchaseItem::create($preparedData);
    }

    private function preparePurchaseItemData(Purchase $purchase, array $itemData, float $expenseShare = 0.0): array
    {
        $item = Item::findOrFail($itemData['item_id']);

        $price           = $itemData['price'];
        $quantity        = $itemData['quantity'];
        $discountPercent = $itemData['discount_percent'] ?? 0;
        $discountAmount  = $itemData['discount_amount'] ?? 0;

        if ($discountPercent > 0) {
            $discountAmount = ($price * $quantity * $discountPercent) / 100;
        }

        $totalPrice        = ($price * $quantity) - $discountAmount;
        $totalPriceUsd     = CurrencyHelper::toUsd($purchase->currency_id, $totalPrice, $purchase->currency_rate);
        $finalTotalCostUsd = $totalPriceUsd + $expenseShare;
        $costPerItemUsd    = $quantity > 0 ? ($finalTotalCostUsd / $quantity) : 0;

        return [
            'purchase_id'          => $purchase->id,
            'item_id'              => $itemData['item_id'],
            'item_code'            => $item->code,
            'price'                => $price,
            'quantity'             => $quantity,
            'discount_percent'     => $discountPercent,
            'discount_amount'      => $discountAmount,
            'total_price'          => $totalPrice,
            'total_price_usd'      => $totalPriceUsd,
            'total_expense_usd'    => $expenseShare,
            'final_total_cost_usd' => $finalTotalCostUsd,
            'cost_per_item_usd'    => $costPerItemUsd,
            'note'                 => $itemData['note'] ?? null,
        ];
    }

    private function validateInventoryBeforeReduction(int $itemId, int $warehouseId, int $reductionAmount, int $oldPurchaseQty, int $newPurchaseQty): void
    {
        $currentInventory = InventoryService::getQuantity($itemId, $warehouseId);
        $inventoryAfterReduction = $currentInventory - $reductionAmount;

        if ($inventoryAfterReduction < 0) {
            $item = Item::find($itemId);
            $itemName = $item ? $item->short_name : "Item #{$itemId}";

            $soldFromThisPurchase  = $oldPurchaseQty - $currentInventory;
            $maxAllowedReduction   = $currentInventory;
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

    private function validateInventoryBeforeDeletion(int $itemId, int $warehouseId, int $purchaseQuantity): void
    {
        $currentInventory = InventoryService::getQuantity($itemId, $warehouseId);

        if ($currentInventory < $purchaseQuantity) {
            $item = Item::find($itemId);
            $itemName = $item ? $item->short_name : "Item #{$itemId}";

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


    private function recalculatePurchaseTotals(Purchase $purchase): void
    {
        $items = $purchase->purchaseItems()->get();

        $subTotal    = $items->sum('total_price');
        $subTotalUsd = $items->sum('total_price_usd');
        $taxUsd      = (float) $purchase->tax_usd;
        $total       = $subTotal - (float) $purchase->discount_amount + CurrencyHelper::fromUsd($purchase->currency_id, $taxUsd, $purchase->currency_rate);
        $totalUsd    = $subTotalUsd - (float) $purchase->discount_amount_usd + $taxUsd;

        $totalExpenseUsd = (float) PurchaseExpense::where('purchase_id', $purchase->id)
            ->join('expense_transactions', 'purchase_expenses.expense_transaction_id', '=', 'expense_transactions.id')
            ->whereNull('expense_transactions.deleted_at')
            ->selectRaw('COALESCE(SUM(expense_transactions.amount_usd + expense_transactions.vat_amount_usd), 0) as total')
            ->value('total');

        $finalTotalUsd = $totalUsd + $totalExpenseUsd;
        $finalTotal    = CurrencyHelper::fromUsd($purchase->currency_id, $finalTotalUsd);

        $purchase->update([
            'sub_total'         => $subTotal,
            'sub_total_usd'     => $subTotalUsd,
            'total'             => $total,
            'total_usd'         => $totalUsd,
            'total_expense_usd' => $totalExpenseUsd,
            'final_total'       => $finalTotal,
            'final_total_usd'   => $finalTotalUsd,
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

                        // Clean up item price history and restore previous item price
                        PriceService::deleteFromPurchase($purchase, $purchaseItem);

                        // Clean up supplier price and restore previous supplier price
                        SupplierItemPriceService::deleteFromPurchase($purchase, $purchaseItem);
                    }
                }

                // Delete all purchase items
                $purchase->purchaseItems()->delete();

                // Delete purchase expenses and their expense transactions (cascade-deletes payments via boot hook)
                $purchase->purchaseExpenses()->with('expenseTransaction')->get()
                    ->each(function ($pe) {
                        $pe->expenseTransaction?->delete();
                        $pe->delete();
                    });

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

                $purchase->update(['status' => 'Delivered', 'delivered_at' => now()]);

                if ($purchase->purchaseItems()->doesntExist()) {
                    throw new \InvalidArgumentException(
                        "Cannot deliver purchase #{$purchase->id}. No items found in this purchase."
                    );
                }

                // Ensure cost_per_item_usd reflects any expenses already on the purchase
                $this->purchaseExpenseService->recalculateItemCosts($purchase);

                foreach ($purchase->purchaseItems()->get() as $purchaseItem) {
                    PriceService::updateFromPurchase($purchase, $purchaseItem, false, null, 0);
                    SupplierItemPriceService::updateOrCreateFromPurchase($purchase, $purchaseItem);
                    InventoryService::add($purchaseItem->item_id, $purchase->warehouse_id, $purchaseItem->quantity);
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