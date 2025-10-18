<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Customers\Customer;
use App\Models\Customers\CustomerBalanceMonthly;
use App\Models\Customers\CustomerBalanceYearly;
use App\Models\Customers\CustomerCreditDebitNote;
use App\Models\Customers\CustomerPayment;
use App\Models\Customers\CustomerReturn;
use App\Models\Customers\CustomerReturnItem;
use App\Models\Customers\Sale;
use App\Models\Customers\SaleItems;
use App\Models\Inventory\Inventory;
use App\Models\Inventory\ItemPrice;
use App\Models\Inventory\ItemPriceHistory;
use App\Models\Items\Item;
use App\Models\Setting;
use App\Models\Suppliers\PurchaseItem;
use App\Models\Suppliers\SupplierItemPrice;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class ClearDataController extends Controller
{
    /**
     * Clear all items and related data (purchase items, sale items, inventory)
     * This will reset the inventory system
     *
     * @return JsonResponse
     */
    public function clearItems(): JsonResponse
    {
        try {
            // Delete related data first (respecting foreign key constraints)
            // Truncate operations are atomic, no transaction needed

            // 1. Clear inventory and item prices
            Inventory::truncate();
            ItemPrice::truncate();
            ItemPriceHistory::truncate();

            // 2. Clear supplier item prices
            SupplierItemPrice::truncate();

            // 3. Clear purchase items
            PurchaseItem::truncate();

            // 4. Clear sale items
            SaleItems::truncate();

            // 5. Clear customer return items
            CustomerReturnItem::truncate();

            // 6. Finally, clear items
            Item::truncate();

            // 7. Delete item code counter (will be auto-created with default on next use)
            Setting::remove('items', 'code_counter');

            return response()->json([
                'message' => 'All items and related data have been cleared successfully',
                'data' => [
                    'items_cleared' => true,
                    'inventory_cleared' => true,
                    'purchase_items_cleared' => true,
                    'sale_items_cleared' => true,
                    'return_items_cleared' => true,
                    'prices_cleared' => true,
                    'code_counter_reset' => true,
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to clear items data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clear all customers and related data
     *
     * @return JsonResponse
     */
    public function clearCustomers(): JsonResponse
    {
        try {
            // Delete related data first (truncate operations are atomic, no transaction needed)

            // 1. Clear customer balances
            CustomerBalanceMonthly::truncate();
            CustomerBalanceYearly::truncate();

            // 2. Clear customer payments
            CustomerPayment::truncate();

            // 3. Clear customer credit/debit notes
            CustomerCreditDebitNote::truncate();

            // 4. Clear customer returns and return items
            CustomerReturnItem::truncate();
            CustomerReturn::truncate();

            // 5. Clear sale items and sales
            SaleItems::truncate();
            Sale::truncate();

            // 6. Finally, clear customers
            Customer::truncate();

            // 7. Delete customer code counter (will be auto-created with default on next use)
            Setting::remove('customers', 'code_counter');

            // 8. Delete sale code counter (will be auto-created with default on next use)
            Setting::remove('sales', 'code_counter');

            return response()->json([
                'message' => 'All customers and related data have been cleared successfully',
                'data' => [
                    'customers_cleared' => true,
                    'sales_cleared' => true,
                    'payments_cleared' => true,
                    'returns_cleared' => true,
                    'balances_cleared' => true,
                    'credit_debit_notes_cleared' => true,
                    'code_counter_reset' => true,
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to clear customers data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clear all sales and related data
     *
     * @return JsonResponse
     */
    public function clearSales(): JsonResponse
    {
        try {
            // Delete related data first (truncate operations are atomic, no transaction needed)

            // 1. Clear sale items
            SaleItems::truncate();

            // 2. Clear sales
            Sale::truncate();

            // 3. Delete sale code counter (will be auto-created with default on next use)
            Setting::remove('sales', 'code_counter');

            // 4. Update customer balances - we may need to recalculate
            // Note: This is a simple approach - in production you may want to
            // recalculate customer balances after clearing sales

            return response()->json([
                'message' => 'All sales and related data have been cleared successfully',
                'data' => [
                    'sales_cleared' => true,
                    'sale_items_cleared' => true,
                    'code_counter_reset' => true,
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to clear sales data',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Clear all data (items, customers, sales) - Full system reset
     * WARNING: This will clear all transactional data
     *
     * @return JsonResponse
     */
    public function clearAll(): JsonResponse
    {
        try {
            // Clear in proper order to respect dependencies
            // Truncate operations are atomic, no transaction needed

            // 1. Clear customer-related transactions
            CustomerBalanceMonthly::truncate();
            CustomerBalanceYearly::truncate();
            CustomerPayment::truncate();
            CustomerCreditDebitNote::truncate();
            CustomerReturnItem::truncate();
            CustomerReturn::truncate();

            // 2. Clear sales
            SaleItems::truncate();
            Sale::truncate();

            // 3. Clear customers
            Customer::truncate();

            // 4. Clear item-related data
            Inventory::truncate();
            ItemPrice::truncate();
            ItemPriceHistory::truncate();
            SupplierItemPrice::truncate();
            PurchaseItem::truncate();

            // 5. Clear items
            Item::truncate();

            // 6. Delete all code counters (will be auto-created with defaults on next use)
            Setting::remove('items', 'code_counter');
            Setting::remove('customers', 'code_counter');
            Setting::remove('sales', 'code_counter');

            return response()->json([
                'message' => 'All data has been cleared successfully - System reset complete',
                'data' => [
                    'items_cleared' => true,
                    'customers_cleared' => true,
                    'sales_cleared' => true,
                    'inventory_cleared' => true,
                    'all_related_data_cleared' => true,
                    'code_counters_reset' => true,
                ]
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to clear all data',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
