<?php

namespace App\Http\Controllers\Api\Reports\Finance;

use App\Helpers\CurrencyHelper;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Accounts\Account;
use App\Models\Customers\Customer;
use App\Models\Customers\CustomerPayment;
use App\Models\Reports\CapitalSnapshot;
use App\Models\Setups\Warehouse;
use App\Models\Suppliers\Purchase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CapitalReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $report = $this->calculateCapitalReport();

        // Get historical snapshots for line graph
        $history = CapitalSnapshot::orderBy('year')
            ->orderBy('month')
            ->get()
            ->map(fn ($snapshot) => [
                'year' => $snapshot->year,
                'month' => $snapshot->month,
                'label' => $snapshot->created_at->format('M Y'),
                'net_capital' => round($snapshot->net_capital, 2),
                'final_result' => round($snapshot->final_result, 2),
            ]);

        return ApiResponse::send('Capital report retrieved successfully', 200, [
            ...$report,
            'history' => $history,
        ]);
    }

    public function calculateCapitalReport(): array
    {
        // 1. Stock value from warehouses where include_in_total_stock = true
        $warehouseIds = Warehouse::includeInStockCount()->pluck('id');

        $stockValue = (float) DB::table('inventories')
            ->join('item_prices', 'inventories.item_id', '=', 'item_prices.item_id')
            ->whereIn('inventories.warehouse_id', $warehouseIds)
            ->sum(DB::raw('inventories.quantity * item_prices.price_usd'));

        // 2. VAT paid on current stock (stock value * default tax rate - VAT already paid in purchases)
        $defaultTaxPercent = (float) DB::table('tax_codes')
            ->where('is_active', true)
            ->where('is_default', true)
            ->value('tax_percent') ?? 0;
        $totalVatOnStock = round($stockValue * $defaultTaxPercent / 100, 2);

        // Subtract VAT already paid on delivered purchases
        $vatPaidInPurchases = (float) Purchase::where('status', 'Delivered')
            ->sum('tax_usd');
        $vatOnStock = round($totalVatOnStock - $vatPaidInPurchases, 2);

        // Per-item VAT calculation (if client wants per-item tax rates in the future):
        // $vatOnStock = (float) DB::table('inventories')
        //     ->join('items', 'inventories.item_id', '=', 'items.id')
        //     ->join('item_prices', 'inventories.item_id', '=', 'item_prices.item_id')
        //     ->join('tax_codes', 'items.tax_code_id', '=', 'tax_codes.id')
        //     ->whereIn('inventories.warehouse_id', $warehouseIds)
        //     ->sum(DB::raw('inventories.quantity * item_prices.price_usd * tax_codes.tax_percent / 100'));

        // 3. Value of purchases not yet received (Waiting or Shipped)
        $pendingPurchasesValue = (float) Purchase::whereIn('status', ['Waiting', 'Shipped'])
            ->sum('total_usd');

        // 4. Net stock cost value
        $netStockValue = $stockValue + $vatOnStock + $pendingPurchasesValue;

        // 5. Unpaid customer balance (negative balance = customer owes money)
        $unpaidCustomerBalance = (float) Customer::where('current_balance', '<', 0)
            ->sum('current_balance');
        $unpaidCustomerBalance = abs($unpaidCustomerBalance);

        // 6. Unapproved payment orders
        $unapprovedPayments = (float) CustomerPayment::pending()
            ->sum('amount_usd');

        // 7. Money from all active accounts (except "Do Not Include in Totals"), converted to USD
        $accountsBalance = (float) Account::active()
            ->includeInTotal()
            ->get(['id', 'currency_id', 'current_balance'])
            ->sum(fn ($account) => CurrencyHelper::toUsd($account->currency_id, $account->current_balance));

        // 8. Net capital
        $netCapital = $netStockValue + $unpaidCustomerBalance + $unapprovedPayments + $accountsBalance;

        // 9. Debt account (accounts with account type "Debt"), converted to USD
        $debtAccount = (float) Account::whereHas('accountType', function ($query) {
            $query->where('name', 'Debt');
        })->get(['id', 'currency_id', 'current_balance'])
            ->sum(fn ($account) => CurrencyHelper::toUsd($account->currency_id, $account->current_balance));

        // 10. Final result
        $finalResult = $netCapital + $debtAccount;

        return [
            'available_stock_value' => round($stockValue, 2),
            'vat_on_current_stock' => round($vatOnStock, 2),
            'pending_purchases_value' => round($pendingPurchasesValue, 2),
            'net_stock_value' => round($netStockValue, 2),
            'unpaid_customer_balance' => round($unpaidCustomerBalance, 2),
            'unapproved_payment_orders' => round($unapprovedPayments, 2),
            'money_in_all_accounts' => round($accountsBalance, 2),
            'net_capital' => round($netCapital, 2),
            'debt_account' => round($debtAccount, 2),
            'final_result' => round($finalResult, 2),
        ];
    }
}
