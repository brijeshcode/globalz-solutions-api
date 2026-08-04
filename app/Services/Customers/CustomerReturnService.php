<?php

namespace App\Services\Customers;

use App\Helpers\CurrencyHelper;
use App\Helpers\CustomersHelper;
use App\Models\Customers\Customer;
use App\Models\Customers\CustomerReturn;
use App\Models\Customers\CustomerReturnItem;
use App\Models\Customers\SaleItems;
use App\Models\Items\Item;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CustomerReturnService
{
    /**
     * Prepare return item data from sale item
     */
    public function prepareReturnItemData(array $itemInput, string $prefix, float $currencyRate): array
    {
        $saleItem =  SaleItems::with(['sale', 'item'])->findOrFail($itemInput['sale_item_id']);
        $returnQuantity = $itemInput['quantity'];


        $isTaxFree = $prefix === CustomerReturn::TAXFREEPREFIX;

        // Taxable base = unit price after discount, before tax (mirrors sale_items.net_sell_price).
        // Derive it from the full-precision price/discount rather than the stored net_sell_price:
        // some legacy sale rows have net_sell_price rounded to 2 dp, and multiplying a rounded
        // per-unit by quantity accumulates the error (e.g. 0.58*24=13.92 instead of 0.584*24=14.02).
        // Prefix-independent.
        $unitTaxable    = $saleItem->price     - $saleItem->unit_discount_amount;
        $unitTaxableUsd = $saleItem->price_usd - $saleItem->unit_discount_amount_usd;

        // Tax fields follow INX (tax-free sale) behaviour: a tax-free (RTX) return strips all tax,
        // clears the label, and its TTC collapses to the taxable base (no tax added).
        $taxPercent   = $isTaxFree ? 0 : $saleItem->tax_percent;
        $taxAmount    = $isTaxFree ? 0 : $saleItem->tax_amount;
        $taxAmountUsd = $isTaxFree ? 0 : $saleItem->tax_amount_usd;
        $taxLabel     = $isTaxFree ? '' : ($saleItem->tax_label ?? 'TVA');

        $ttcPrice    = $isTaxFree ? $unitTaxable    : $saleItem->ttc_price;
        $ttcPriceUsd = $isTaxFree ? $unitTaxableUsd : $saleItem->ttc_price_usd;

        // Copy all data from sale item and recalculate based on return quantity
        return [
            'item_code' => $saleItem->item_code,
            'item_id' => $saleItem->item_id,
            'sale_id' => $saleItem->sale_id,
            'sale_item_id' => $saleItem->id,
            'quantity' => $returnQuantity,

            // Prices (per unit from sale)
            'price' => $saleItem->price,
            'price_usd' => $saleItem->price_usd,

            // Tax details
            'tax_percent' => $taxPercent,
            'tax_label' => $taxLabel,
            'tax_amount' => $taxAmount,
            'tax_amount_usd' => $taxAmountUsd,

            // TTC price (per unit)
            'ttc_price' => $ttcPrice,
            'ttc_price_usd' => $ttcPriceUsd,

            // Taxable base per unit / line total
            'unit_taxable_amount' => $unitTaxable,
            'unit_taxable_amount_usd' => $unitTaxableUsd,
            'total_taxable_amount' => $unitTaxable * $returnQuantity,
            'total_taxable_amount_usd' => $unitTaxableUsd * $returnQuantity,

            // Tax total = per-unit tax * quantity (0 for tax-free RTX)
            'total_tax_amount' => $taxAmount * $returnQuantity,
            'total_tax_amount_usd' => $taxAmountUsd * $returnQuantity,

            // Discount details
            'discount_percent' => $saleItem->discount_percent,
            'unit_discount_amount' => $saleItem->unit_discount_amount,
            'unit_discount_amount_usd' => $saleItem->unit_discount_amount_usd,

            // Calculate total discount amount for return quantity
            'discount_amount' => $saleItem->unit_discount_amount * $returnQuantity,
            'discount_amount_usd' => $saleItem->unit_discount_amount_usd * $returnQuantity,

            // Total price = TTC per unit * quantity (tax-inclusive; equals taxable base for RTX)
            'total_price' => $ttcPrice * $returnQuantity,
            'total_price_usd' => $ttcPriceUsd * $returnQuantity,

            // Calculate return profit (negative because it's a return)
            // total_price_usd - (cost_price * quantity) - we use the cost from sale item
            // 'total_profit' => ($saleItem->price_usd * $returnQuantity - ($saleItem->unit_discount_amount_usd * $returnQuantity)) - ($saleItem->cost_price * $returnQuantity),
            'total_profit' => $saleItem->unit_profit * $returnQuantity,

            // Volume and weight
            'total_volume_cbm' => ($saleItem->item->volume_cbm ?? 0) * $returnQuantity,
            'total_weight_kg' => ($saleItem->item->weight_kg ?? 0) * $returnQuantity,

            // Note
            'note' => $itemInput['note'] ?? null,
        ];
    }

    /**
     * Compute the taxable-base / tax breakdown for a direct return item (no linked sale item),
     * from its own price/discount/total figures. Enforces net + tax = total_price.
     *
     * @return array{unit_taxable_amount: float, unit_taxable_amount_usd: float, total_taxable_amount: float, total_taxable_amount_usd: float, total_tax_amount: float, total_tax_amount_usd: float}
     */
    public static function directTaxableBreakdown(array $item): array
    {
        $quantity        = (float) ($item['quantity'] ?? 0);
        $price           = (float) ($item['price'] ?? 0);
        $priceUsd        = (float) ($item['price_usd'] ?? 0);
        $discountPercent = (float) ($item['discount_percent'] ?? 0);

        // Unit discount: percent-based if given, otherwise the fixed per-unit amount
        $unitDiscount    = $discountPercent > 0 ? $price * ($discountPercent / 100) : (float) ($item['unit_discount_amount'] ?? 0);
        $unitDiscountUsd = $discountPercent > 0 ? $priceUsd * ($discountPercent / 100) : (float) ($item['unit_discount_amount_usd'] ?? 0);

        // Taxable base = unit price after discount, before tax
        $unitTaxable     = $price - $unitDiscount;
        $unitTaxableUsd  = $priceUsd - $unitDiscountUsd;
        $totalTaxable    = $unitTaxable * $quantity;
        $totalTaxableUsd = $unitTaxableUsd * $quantity;

        // Tax = tax-inclusive line total - taxable base (0 for tax-free lines)
        $totalPrice    = (float) ($item['total_price'] ?? 0);
        $totalPriceUsd = (float) ($item['total_price_usd'] ?? 0);

        return [
            'unit_taxable_amount'      => $unitTaxable,
            'unit_taxable_amount_usd'  => $unitTaxableUsd,
            'total_taxable_amount'     => $totalTaxable,
            'total_taxable_amount_usd' => $totalTaxableUsd,
            'total_tax_amount'         => $totalPrice - $totalTaxable,
            'total_tax_amount_usd'     => $totalPriceUsd - $totalTaxableUsd,
        ];
    }

    /**
     * Create a new customer return with items
     */
    public function createCustomerReturnWithItems(array $returnData, array $items = []): CustomerReturn
    {
        try {
            return DB::transaction(function () use ($returnData, $items) {
                // Create the customer return
                $customerReturn = CustomerReturn::create($returnData);

                // Process customer return items
                if (!empty($items)) {
                    $this->processCustomerReturnItems($customerReturn, $items);
                }

                return $customerReturn->fresh();
            });
        } catch (\Exception $e) {
            Log::error('Failed to create customer return with items', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'return_data' => $returnData,
                'items_count' => count($items),
            ]);

            throw new \RuntimeException(
                "Failed to create customer return: " . $e->getMessage() .
                ". All changes have been rolled back.",
                0,
                $e
            );
        }
    }

    /**
     * Update customer return with items
     */
    public function updateCustomerReturnWithItems(CustomerReturn $customerReturn, array $returnData, array $items = []): CustomerReturn
    {
        try {
            return DB::transaction(function () use ($customerReturn, $returnData, $items) {
                // Check if return was already received - can't update received returns
                if ($customerReturn->isReceived()) {
                    throw new \InvalidArgumentException(
                        "Cannot update customer return that has already been received. " .
                        "Please delete and create a new return if changes are needed."
                    );
                }

                // Update customer return data
                $customerReturn->update($returnData);

                // Update items
                $this->updateCustomerReturnItems($customerReturn, $items);

                return $customerReturn->fresh();
            });
        } catch (\InvalidArgumentException $e) {
            Log::warning('Customer return update validation failed', [
                'error' => $e->getMessage(),
                'customer_return_id' => $customerReturn->id,
                'customer_return_code' => $customerReturn->code ?? 'N/A',
            ]);

            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to update customer return with items', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'customer_return_id' => $customerReturn->id,
                'customer_return_code' => $customerReturn->code ?? 'N/A',
            ]);

            throw new \RuntimeException(
                "Failed to update customer return #{$customerReturn->id}: " . $e->getMessage() .
                ". All changes have been rolled back.",
                0,
                $e
            );
        }
    }

    /**
     * Process customer return items for creation
     */
    private function processCustomerReturnItems(CustomerReturn $customerReturn, array $items): void
    {
        foreach ($items as $itemData) {
            CustomerReturnItem::create([
                'customer_return_id' => $customerReturn->id,
                'item_code' => $itemData['item_code'],
                'item_id' => $itemData['item_id'] ?? null,
                'quantity' => $itemData['quantity'],
                'price' => $itemData['price'],
                'discount_percent' => $itemData['discount_percent'] ?? 0,
                'unit_discount_amount' => $itemData['unit_discount_amount'] ?? 0,
                'discount_amount' => $itemData['discount_amount'] ?? 0,
                'tax_percent' => $itemData['tax_percent'] ?? 0,
                'ttc_price' => $itemData['ttc_price'] ?? 0,
                'total_price' => $itemData['total_price'] ?? 0,
                'total_price_usd' => $itemData['total_price_usd'] ?? 0,
                'total_volume_cbm' => $itemData['total_volume_cbm'] ?? 0,
                'total_weight_kg' => $itemData['total_weight_kg'] ?? 0,
                'note' => $itemData['note'] ?? null,
            ]);
        }
    }

    /**
     * Update customer return items individually
     */
    private function updateCustomerReturnItems(CustomerReturn $customerReturn, array $items): void
    {
        $processedItemIds = [];

        foreach ($items as $itemData) {
            if (isset($itemData['id'])) {
                // Update existing item
                $returnItem = CustomerReturnItem::where('id', $itemData['id'])
                    ->where('customer_return_id', $customerReturn->id)
                    ->first();

                if ($returnItem) {
                    $returnItem->update([
                        'item_code' => $itemData['item_code'],
                        'item_id' => $itemData['item_id'] ?? null,
                        'quantity' => $itemData['quantity'],
                        'price' => $itemData['price'],
                        'discount_percent' => $itemData['discount_percent'] ?? 0,
                        'unit_discount_amount' => $itemData['unit_discount_amount'] ?? 0,
                        'discount_amount' => $itemData['discount_amount'] ?? 0,
                        'tax_percent' => $itemData['tax_percent'] ?? 0,
                        'ttc_price' => $itemData['ttc_price'] ?? 0,
                        'total_price' => $itemData['total_price'] ?? 0,
                        'total_price_usd' => $itemData['total_price_usd'] ?? 0,
                        'total_volume_cbm' => $itemData['total_volume_cbm'] ?? 0,
                        'total_weight_kg' => $itemData['total_weight_kg'] ?? 0,
                        'note' => $itemData['note'] ?? $returnItem->note,
                    ]);

                    $processedItemIds[] = $returnItem->id;
                }
            } else {
                // Create new item
                $newItem = CustomerReturnItem::create([
                    'customer_return_id' => $customerReturn->id,
                    'item_code' => $itemData['item_code'],
                    'item_id' => $itemData['item_id'] ?? null,
                    'quantity' => $itemData['quantity'],
                    'price' => $itemData['price'],
                    'discount_percent' => $itemData['discount_percent'] ?? 0,
                    'unit_discount_amount' => $itemData['unit_discount_amount'] ?? 0,
                    'discount_amount' => $itemData['discount_amount'] ?? 0,
                    'tax_percent' => $itemData['tax_percent'] ?? 0,
                    'ttc_price' => $itemData['ttc_price'] ?? 0,
                    'total_price' => $itemData['total_price'] ?? 0,
                    'total_price_usd' => $itemData['total_price_usd'] ?? 0,
                    'total_volume_cbm' => $itemData['total_volume_cbm'] ?? 0,
                    'total_weight_kg' => $itemData['total_weight_kg'] ?? 0,
                    'note' => $itemData['note'] ?? null,
                ]);
                $processedItemIds[] = $newItem->id;
            }
        }

        // Delete removed items
        $customerReturn->items()
            ->whereNotIn('id', $processedItemIds)
            ->delete();
    }

    /**
     * Mark customer return as received and update inventory
     */
    public function markAsReceived(CustomerReturn $customerReturn, int $userId, ?string $note = null): CustomerReturn
    {
        try {
            return DB::transaction(function () use ($customerReturn, $userId, $note) {
                // Validate return is approved
                if (!$customerReturn->isApproved()) {
                    throw new \InvalidArgumentException('Return must be approved before marking as received');
                }

                // Validate not already received
                if ($customerReturn->isReceived()) {
                    throw new \InvalidArgumentException('Return is already marked as received');
                }

                // Update return status
                $customerReturn->update([
                    'return_received_by' => $userId,
                    'return_received_at' => now(),
                    'return_received_note' => $note
                ]);

                // Add inventory back for each returned item
                foreach ($customerReturn->items as $returnItem) {
                    if ($returnItem->item_id && $returnItem->quantity > 0) {
                        InventoryService::add(
                            $returnItem->item_id,
                            $customerReturn->warehouse_id,
                            $returnItem->quantity
                        );
                    }
                }

                return $customerReturn->fresh();
            });
        } catch (\InvalidArgumentException $e) {
            Log::warning('Mark customer return as received validation failed', [
                'error' => $e->getMessage(),
                'customer_return_id' => $customerReturn->id,
            ]);

            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to mark customer return as received', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'customer_return_id' => $customerReturn->id,
            ]);

            throw new \RuntimeException(
                "Failed to mark customer return #{$customerReturn->id} as received: " . $e->getMessage() .
                ". All changes have been rolled back.",
                0,
                $e
            );
        }
    }

    /**
     * Delete a customer return and adjust inventory if it was received
     */
    public function deleteCustomerReturn(CustomerReturn $customerReturn): void
    {
        try {
            DB::transaction(function () use ($customerReturn) {
                // Load items before deletion
                $returnItems = $customerReturn->items;
                $wasReceived = $customerReturn->isReceived();

                // If the return was received, reverse inventory and customer balance
                if ($wasReceived) {
                    foreach ($returnItems as $returnItem) {
                        if ($returnItem->item_id && $returnItem->quantity > 0) {
                            InventoryService::subtract(
                                $returnItem->item_id,
                                $customerReturn->warehouse_id,
                                $returnItem->quantity
                            );
                        }
                    }

                    CustomersHelper::removeBalance(
                        Customer::find($customerReturn->customer_id),
                        (float) $customerReturn->total_usd
                    );
                }

                // Delete the customer return
                $customerReturn->delete();
            });
        } catch (\Exception $e) {
            Log::error('Failed to delete customer return', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'customer_return_id' => $customerReturn->id,
            ]);

            throw new \RuntimeException(
                "Failed to delete customer return #{$customerReturn->id}: " . $e->getMessage() .
                ". All changes have been rolled back.",
                0,
                $e
            );
        }
    }

    /**
     * Restore a deleted customer return and adjust inventory if it was received
     */
    public function restoreCustomerReturn(CustomerReturn $customerReturn): void
    {
        try {
            DB::transaction(function () use ($customerReturn) {
                // Restore the return
                $customerReturn->restore();
                $customerReturn->items()->withTrashed()->restore();

                // Refresh to get current state
                $customerReturn->refresh();

                // If the return was marked as received before deletion, add inventory back
                if ($customerReturn->isReceived()) {
                    foreach ($customerReturn->items as $returnItem) {
                        if ($returnItem->item_id && $returnItem->quantity > 0) {
                            InventoryService::add(
                                $returnItem->item_id,
                                $customerReturn->warehouse_id,
                                $returnItem->quantity
                            );
                        }
                    }
                }
            });
        } catch (\Exception $e) {
            Log::error('Failed to restore customer return', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'customer_return_id' => $customerReturn->id,
            ]);

            throw new \RuntimeException(
                "Failed to restore customer return #{$customerReturn->id}: " . $e->getMessage() .
                ". All changes have been rolled back.",
                0,
                $e
            );
        }
    }
}
