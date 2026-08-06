<?php

namespace App\Http\Controllers\Api\Reports\Finance;

use App\Helpers\CurrencyHelper;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Accounts\Account;
use App\Models\Employees\AdvanceLoan;
use App\Models\Employees\EmployeeCreditDebitNote;
use App\Models\Employees\Salary;
use App\Models\Customers\Customer;
use App\Models\Customers\CustomerPayment;
use App\Models\Customers\CustomerReturn;
use App\Models\Setups\Supplier;
use App\Models\Suppliers\Purchase;
use App\Models\Vehicle\GasStation;
use App\Services\Reports\Finance\ProfitReportService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class BalanceSheetController extends Controller
{
    public function __construct(private ProfitReportService $profitReportService)
    {
    }

    /**
     * Balance Sheet report.
     *
     * Every figure is a live snapshot (current balances / lifetime profit). The as_of_date
     * is accepted and echoed back for the client, but does not yet rewind the numbers — that
     * arrives once weekly snapshots are stored. Assets and Liabilities & Equity are each derived
     * independently (Retained Earnings is real accumulated profit, not a balancing plug), so the
     * two sides are not forced to reconcile; balance_difference exposes any gap.
     */
    public function index(Request $request): JsonResponse
    {
        $asOfDate = $request->get('as_of_date', now()->toDateString());

        // Sign-dynamic lines: computed once, then routed to whichever side their sign dictates.
        $employeeLoans = $this->employeeLoansBalance();
        $employeeCreditDebit = $this->employeeCreditDebitBalance();
        $returnOrders = $this->returnOrdersNotApproved();

        // ---- ASSETS ----
        $currentAssets = $this->section([
            $this->makeLine('Cash & Bank Accounts (positive only)', $this->cashAndBankPositive()),
            $this->makeLine('Payments Orders still not approved', $this->paymentsOrdersNotApproved()),
            $this->makeLine('Inventory / Stock on Hand', $this->inventoryStockOnHand()),
            $this->makeLine('Items Purchased Not Received', $this->itemsPurchasedNotReceived()),
            $this->makeLine('VAT paid on Current Stock', $this->vatPaidOnCurrentStock()),
        ]);

        $accountsReceivable = $this->section(array_merge([
            $this->makeLine('Money customers owe us (Customers Balances)', $this->customersBalances()),
            $this->makeLine('Return Orders (Still not approved)', $returnOrders),
            $this->makeLine("Supplier's Balances (positive only)", $this->suppliersBalancesAsset()),
            $this->makeLine('Goverment VAT (positive only)', $this->governmentVat()),
            $this->makeLine("Gas Station's Balances (positive only)", $this->gasStationBalancesPositive()),
        ],
            // Employee lines land here only when their net is an asset (>= 0).
            $employeeLoans['value'] >= 0 ? [$this->makeLine('Employees Balances — Loans', $employeeLoans)] : [],
            $employeeCreditDebit['value'] >= 0 ? [$this->makeLine('Employees Balances — Credit/Debit Notes', $employeeCreditDebit)] : [],
        ));

        $nonCurrentAssets = $this->section([
            $this->makeLine('Equipment & Hardware', $this->equipmentAndHardware()),
            $this->makeLine('Deposit on rental', $this->depositOnRental()),
        ]);

        $totalAssets = round($currentAssets['subtotal'] + $accountsReceivable['subtotal'] + $nonCurrentAssets['subtotal'], 2);

        // ---- LIABILITIES & EQUITY ---- (liability/equity values are stored negative)
        $currentLiabilities = $this->section([
            $this->makeLine('Cash & Bank Accounts (negative only)', $this->cashAndBankNegative()),
            // Return Orders mirrored: the refund owed to the customer (negated).
            $this->makeLine('Return Orders (Still not approved)', [
                'value' => -$returnOrders['value'],
                'note' => $returnOrders['note'],
            ]),
        ]);

        $accountsPayable = $this->section(array_merge([
            $this->makeLine('Goverment VAT owed (negative only)', $this->governmentVat()),
            $this->makeLine("Supplier's Balances (negative only)", $this->suppliersBalancesLiability()),
            $this->makeLine("Gas Station's Balances (negative only)", $this->gasStationBalancesNegative()),
        ],
            // Employee lines land here only when their net is a liability (< 0).
            $employeeLoans['value'] < 0 ? [$this->makeLine('Employees Balances — Loans', $employeeLoans)] : [],
            $employeeCreditDebit['value'] < 0 ? [$this->makeLine('Employees Balances — Credit/Debit Notes', $employeeCreditDebit)] : [],
        ));

        $ownersEquity = $this->section([
            $this->makeLine('Initial Capital Invested', $this->initialCapitalInvested()),
            $this->makeLine('Retained Earnings (Accumulated lifetime profits)', $this->retainedEarnings()),
        ]);

        $totalLiabilitiesAndEquity = round(
            $currentLiabilities['subtotal'] + $accountsPayable['subtotal'] + $ownersEquity['subtotal'],
            2
        );

        return ApiResponse::send('Balance sheet retrieved successfully', 200, [
            'as_of_date' => $asOfDate,
            'assets' => [
                'current_assets' => $currentAssets,
                'accounts_receivable' => $accountsReceivable,
                'non_current_assets' => $nonCurrentAssets,
                'total' => $totalAssets,
            ],
            'liabilities_and_equity' => [
                'current_liabilities' => $currentLiabilities,
                'accounts_payable' => $accountsPayable,
                'owners_equity' => $ownersEquity,
                // Sum of the (negative) section subtotals, so this stays negative — matching
                // the mockup where Liabilities & Equity is shown as a negative total.
                'total' => $totalLiabilitiesAndEquity,
            ],
            // Assets plus the (already negative) Liabilities & Equity total: ≈ 0 when the sheet
            // balances. Non-zero means the two independently-derived sides don't fully reconcile
            // (expected small rounding / real drift, e.g. -0.54).
            'balance_difference' => round($totalAssets + $totalLiabilitiesAndEquity, 2),
        ]);
    }

    /**
     * Wrap a line method's {value, note} result into a labelled, rounded line.
     *
     * @param  array{value: float, note: string}  $result
     * @return array{label: string, value: float, note: string}
     */
    private function makeLine(string $label, array $result): array
    {
        return [
            'label' => $label,
            'value' => round((float) $result['value'], 2),
            'note' => $result['note'],
        ];
    }

    /**
     * Wrap a set of lines with their rounded subtotal.
     *
     * @param  array<int, array{label: string, value: float, note: string}>  $lines
     */
    private function section(array $lines): array
    {
        return [
            'lines' => $lines,
            'subtotal' => round(array_sum(array_column($lines, 'value')), 2),
        ];
    }

    /**
     * Cash & Bank Accounts (positive only) — Current Assets line.
     *
     * @return array{value: float, note: string}
     */
    private function cashAndBankPositive(): array
    {
        $value = (float) Account::query()
            ->active()
            ->includeInTotal()
            ->where('current_balance', '>', 0)
            ->get(['id', 'currency_id', 'current_balance'])
            ->sum(fn ($account) => CurrencyHelper::toUsd($account->currency_id, $account->current_balance));

        return [
            'value' => $value,
            'note' => 'Total of the current balance of all active accounts (included in total) that are in credit '
                . '(positive), each converted to USD. This is the cash and bank money the company currently holds.',
        ];
    }

    /**
     * Cash & Bank Accounts (negative only) — Current Liabilities line.
     *
     * @return array{value: float, note: string}
     */
    private function cashAndBankNegative(): array
    {
        $value = (float) Account::query()
            ->active()
            ->includeInTotal()
            ->where('current_balance', '<', 0)
            ->get(['id', 'currency_id', 'current_balance'])
            ->sum(fn ($account) => CurrencyHelper::toUsd($account->currency_id, $account->current_balance));

        return [
            'value' => $value,
            'note' => 'Total of the current balance of all active accounts (included in total) that are overdrawn '
                . '(negative), each converted to USD. Shown as a liability because the company owes this money.',
        ];
    }

    /**
     * Payments Orders still not approved — Current Assets line.
     *
     * @return array{value: float, note: string}
     */
    private function paymentsOrdersNotApproved(): array
    {
        $value = (float) CustomerPayment::query()
            ->pending()
            ->sum('amount_usd');

        return [
            'value' => $value,
            'note' => 'Total (USD) of customer payment orders that have been recorded but not yet approved.',
        ];
    }

    /**
     * Money customers owe us (Customers Balances) — Accounts Receivable line.
     *
     * @return array{value: float, note: string}
     */
    private function customersBalances(): array
    {
        // In the DB a customer's current_balance is negative when they owe the company
        // (how much to collect from them). The balance sheet shows it the other way round,
        // so the sign is flipped: money owed to us becomes a positive asset, and vice versa.
        $value = -1 * (float) Customer::query()
            ->active()
            ->sum('current_balance');

        return [
            'value' => $value,
            'note' => 'Total outstanding balance of all active customers — the money customers owe the company. '
                . 'Stored negative in the system (amount to collect), shown here with the sign flipped so it reads positive.',
        ];
    }

    /**
     * Inventory / Stock on Hand — Assets line.
     * SUM(quantity * item_prices.price_usd) across active items in active warehouses.
     * Multiplication is done in SQL so rounding happens once on the total, not per row.
     *
     * @return array{value: float, note: string}
     */
    private function inventoryStockOnHand(): array
    {
        $value = (float) DB::table('inventories')
            ->join('items', 'inventories.item_id', '=', 'items.id')
            ->join('item_prices', 'inventories.item_id', '=', 'item_prices.item_id')
            ->join('warehouses', 'inventories.warehouse_id', '=', 'warehouses.id')
            ->where('items.is_active', true)
            ->where('warehouses.is_active', true)
            ->where('warehouses.include_in_total_stock', true)
            ->whereNull('items.deleted_at')
            ->whereNull('warehouses.deleted_at')
            ->sum(DB::raw('inventories.quantity * item_prices.price_usd'));

        return [
            'value' => $value,
            'note' => 'Value of stock on hand: for every active item in an active warehouse (counted in total stock), '
                . 'quantity multiplied by its unit price, then summed.',
        ];
    }

    /**
     * Return Orders (Still not approved) — appears on both sides of the sheet.
     * Returned as a positive magnitude: shown as-is under Assets (stock coming back
     * into the warehouse) and negated under Current Liabilities (refund the company
     * owes the customer). Same figure, mirrored.
     *
     * @return array{value: float, note: string}
     */
    private function returnOrdersNotApproved(): array
    {
        $value = (float) CustomerReturn::query()
            ->pending()
            ->sum('total_usd');

        return [
            'value' => $value,
            'note' => 'Total (USD) of customer return orders not yet approved. It appears on both sides: as an asset '
                . '(stock coming back into the warehouse) and as a liability (the refund the company owes the customer).',
        ];
    }

    /**
     * Items Purchased Not Received — Assets line.
     *
     * @return array{value: float, note: string}
     */
    private function itemsPurchasedNotReceived(): array
    {
        $value = (float) Purchase::query()
            ->where('status', '!=', 'Delivered')
            ->sum('final_total_usd');

        return [
            'value' => $value,
            'note' => 'Total value (goods plus expenses) of purchases that are not yet delivered '
                . '(still Waiting or Shipped).',
        ];
    }

    /**
     * Supplier's Balances (asset side) — Assets line.
     *
     * @return array{value: float, note: string}
     */
    private function suppliersBalancesAsset(): array
    {
        $value = abs((float) Supplier::query()
            ->active()
            ->where('current_balance', '<', 0)
            ->get(['id', 'currency_id', 'current_balance'])
            ->sum(fn ($supplier) => CurrencyHelper::toUsd($supplier->currency_id, $supplier->current_balance)));

        return [
            'value' => $value,
            'note' => 'Active suppliers whose balance is negative — the supplier owes us or we have prepaid, '
                . 'each converted to USD. Shown as an asset (the negative sign is dropped).',
        ];
    }

    /**
     * Supplier's Balances (liability side) — Current Liabilities line.
     *
     * @return array{value: float, note: string}
     */
    private function suppliersBalancesLiability(): array
    {
        $value = -1 * (float) Supplier::query()
            ->active()
            ->where('current_balance', '>', 0)
            ->get(['id', 'currency_id', 'current_balance'])
            ->sum(fn ($supplier) => CurrencyHelper::toUsd($supplier->currency_id, $supplier->current_balance));

        return [
            'value' => $value,
            'note' => 'Active suppliers whose balance is positive — the company owes the supplier, '
                . 'each converted to USD. Shown as a liability (negative).',
        ];
    }

    /**
     * Gas Station's Balances (positive only) — Assets line.
     *
     * @return array{value: float, note: string}
     */
    private function gasStationBalancesPositive(): array
    {
        $value = (float) GasStation::query()
            ->where('is_active', true)
            ->where('balance', '>', 0)
            ->sum('balance');

        return [
            'value' => $value,
            'note' => 'Active gas stations with a positive balance (credit the company holds). Shown as an asset.',
        ];
    }

    /**
     * Gas Station's Balances (negative only) — Current Liabilities line.
     *
     * @return array{value: float, note: string}
     */
    private function gasStationBalancesNegative(): array
    {
        $value = (float) GasStation::query()
            ->where('is_active', true)
            ->where('balance', '<', 0)
            ->sum('balance');

        return [
            'value' => $value,
            'note' => 'Active gas stations with a negative balance (money the company owes them). Shown as a liability.',
        ];
    }

    /**
     * Employees Balances — Loans line.
     * Overall net loan position across all active employees:
     *   loans given (AdvanceLoan) - loan repayments deducted from salaries (Salary.advance_payment).
     * A positive net is an asset (employees owe the company); negative is a liability.
     *
     * @return array{value: float, note: string}
     */
    private function employeeLoansBalance(): array
    {
        $loansGiven = (float) AdvanceLoan::query()
            ->whereHas('employee', fn ($q) => $q->active())
            ->sum('amount_usd');

        $loansRepaid = (float) Salary::query()
            ->whereHas('employee', fn ($q) => $q->active())
            ->where('advance_payment', '>', 0)
            ->sum('advance_payment');

        $net = $loansGiven - $loansRepaid;

        return [
            'value' => $net,
            'note' => 'Loans given to active employees (' . number_format($loansGiven, 2)
                . ') minus loan repayments deducted from their salaries (' . number_format($loansRepaid, 2)
                . '). A positive figure is an asset (employees owe the company); a negative figure is a liability.',
        ];
    }

    /**
     * Employees Balances — Credit/Debit Notes line.
     * Overall net of employee credit/debit notes across all active employees:
     *   debit notes - credit notes (amount_usd).
     * A positive net is treated as an asset (employee owes the company); negative as a liability.
     *
     * NOTE: this debit - credit sign convention is still to be CONFIRMED with the client.
     *
     * @return array{value: float, note: string}
     */
    private function employeeCreditDebitBalance(): array
    {
        $debit = (float) EmployeeCreditDebitNote::query()
            ->debit()
            ->whereHas('employee', fn ($q) => $q->active())
            ->sum('amount_usd');

        $credit = (float) EmployeeCreditDebitNote::query()
            ->credit()
            ->whereHas('employee', fn ($q) => $q->active())
            ->sum('amount_usd');

        $net = $debit - $credit;

        return [
            'value' => $net,
            'note' => 'Employee debit notes (' . number_format($debit, 2)
                . ') minus credit notes (' . number_format($credit, 2)
                . ') for active employees. Sign convention (debit − credit): a positive figure is an asset '
                . '(employee owes the company), negative is a liability. NOTE: this convention is pending confirmation with the client.',
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Lines with no data source yet (placeholders)
    |--------------------------------------------------------------------------
    | These lines exist in the client's balance-sheet design but we do not yet
    | have a table/model to derive them from. Each returns 0 with a note so the
    | line still renders and the gap is visible. Replace the body with the real
    | query once the data source is agreed with the client.
    */

    /**
     * VAT paid on Current Stock — Assets line.
     * SKIPPED: formula not yet decided (input VAT recoverable on stock on hand).
     * Likely VAT rate applied to Inventory / Stock on Hand, but the rate source
     * (per-item tax code vs flat rate) still needs to be confirmed with the client.
     *
     * @return array{value: float, note: string}
     */
    private function vatPaidOnCurrentStock(): array
    {
        return [
            'value' => 0.0,
            'note' => 'Not yet calculated — the VAT-on-current-stock formula and rate source are pending confirmation with the client.',
        ];
    }

    /**
     * Government VAT — appears on both sides (VAT recoverable vs VAT owed).
     * SKIPPED: data source / formula not yet decided.
     *
     * @return array{value: float, note: string}
     */
    private function governmentVat(): array
    {
        return [
            'value' => 0.0,
            'note' => 'Not yet calculated — the Government VAT figure (paid vs owed) has no agreed data source yet.',
        ];
    }

    /**
     * Equipment & Hardware — Non-Current Assets line.
     * MISSING: there is no equipment/fixed-asset table in the system yet, so this
     * cannot be derived. Needs a data source (a fixed-assets register or a manual value).
     *
     * @return array{value: float, note: string}
     */
    private function equipmentAndHardware(): array
    {
        return [
            'value' => 0.0,
            'note' => 'Not available — the system has no equipment / fixed-asset register to derive this from yet.',
        ];
    }

    /**
     * Deposit on rental — Non-Current Assets line.
     * MISSING: no rental-deposit record exists in the system yet.
     *
     * @return array{value: float, note: string}
     */
    private function depositOnRental(): array
    {
        return [
            'value' => 0.0,
            'note' => 'Not available — there is no rental-deposit record in the system to derive this from yet.',
        ];
    }

    /**
     * Initial Capital Invested — Owner's Equity line.
     * Sum of the current balance of every account whose account type is "Debt",
     * converted to USD per account (accounts can be in different currencies).
     * Mirrors the existing CapitalReportController "Debt account" calculation.
     *
     * @return array{value: float, note: string}
     */
    private function initialCapitalInvested(): array
    {
        $value = (float) Account::query()
            ->whereHas('accountType', fn ($q) => $q->where('name', 'Debt'))
            ->get(['id', 'currency_id', 'current_balance'])
            ->sum(fn ($account) => CurrencyHelper::toUsd($account->currency_id, $account->current_balance));

        return [
            'value' => $value,
            'note' => 'Total current balance of all accounts of type "Debt", each converted to USD. '
                . 'This represents the initial capital invested (same basis as the Capital report).',
        ];
    }

    /**
     * Retained Earnings (Accumulated lifetime profits) — Owner's Equity line.
     * Sum of final_net_profit across every year, from the shared ProfitReportService
     * (the same figure the Monthly Profit report uses, accumulated over all years).
     *
     * @return array{value: float, note: string}
     */
    private function retainedEarnings(): array
    {
        $value = $this->profitReportService->getLifetimeNetProfit();

        return [
            'value' => $value,
            'note' => 'Accumulated lifetime profit: the net profit of every year added together, using the same '
                . 'final net profit figure as the Monthly Profit report (sales profit, minus returns, expenses, '
                . 'salaries, credit notes and gas station payments).',
        ];
    }
}
