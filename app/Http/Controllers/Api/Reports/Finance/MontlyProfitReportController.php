<?php

namespace App\Http\Controllers\Api\Reports\Finance;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class MontlyProfitReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $year = $request->get('year', now()->year);

        $reportData = $this->getMonthlyProfitData($year);

        return ApiResponse::send('Monthly profit report retrieved successfully', 200, $reportData);
    }

    private function getMonthlyProfitData(int $year): array
    {
        // Get sales data (approved sales only) - exclude tax from total
        $salesQuery = DB::table('sales')
            ->selectRaw('
                MONTH(date) as month,
                YEAR(date) as year,
                SUM(total_usd - total_tax_amount_usd) as total_sales,
                SUM(total_tax_amount_usd) as total_sale_tax,
                SUM(total_profit) as total_profit
            ')
            ->whereYear('date', $year)
            ->whereNull('deleted_at')
            ->whereNotNull('approved_by')
            ->groupBy('month', 'year');

        $salesData = $salesQuery->get()->keyBy('month');

        // Get returns data (approved and received returns only) - exclude tax and get profit
        $returnsQuery = DB::table('customer_return_items')
            ->join('customer_returns', 'customer_return_items.customer_return_id', '=', 'customer_returns.id')
            ->selectRaw('
                MONTH(customer_returns.date) as month,
                YEAR(customer_returns.date) as year,
                SUM(customer_return_items.total_price_usd - (customer_return_items.tax_amount_usd * customer_return_items.quantity)) as total_returns,
                SUM(customer_return_items.tax_amount_usd * customer_return_items.quantity) as total_return_tax,
                SUM(customer_return_items.total_profit) as total_profit
            ')
            ->whereYear('customer_returns.date', $year)
            ->whereNull('customer_returns.deleted_at')
            ->whereNull('customer_return_items.deleted_at')
            ->whereNotNull('customer_returns.return_received_by')
            ->groupBy('month', 'year');

        $returnsData = $returnsQuery->get()->keyBy('month');

        // Get expenses data (excluding categories marked as exclude_from_profit)
        $expensesQuery = DB::table('expense_transactions')
            ->leftJoin('expense_categories', 'expense_transactions.expense_category_id', '=', 'expense_categories.id')
            ->selectRaw('
                MONTH(expense_transactions.date) as month,
                YEAR(expense_transactions.date) as year,
                SUM(expense_transactions.amount_usd) as total_expenses
            ')
            ->whereYear('expense_transactions.date', $year)
            ->whereNull('expense_transactions.deleted_at')
            ->where(function ($query) {
                $query->whereNull('expense_categories.exclude_from_profit')
                    ->orWhere('expense_categories.exclude_from_profit', false);
            })
            ->groupBy('month', 'year');

        $expensesData = $expensesQuery->get()->keyBy('month');

        // Get salaries data (by month and year fields)
        $salariesQuery = DB::table('salaries')
            ->selectRaw('
                month,
                year,
                SUM(amount_usd) as total_salaries
            ')
            ->where('year', $year)
            ->whereNull('deleted_at')
            ->groupBy('month', 'year');

        $salariesData = $salariesQuery->get()->keyBy('month');

        // Get credit notes data (only credit type)
        $creditNotesQuery = DB::table('customer_credit_debit_notes')
            ->selectRaw('
                MONTH(date) as month,
                YEAR(date) as year,
                SUM(amount_usd) as total_credit_notes
            ')
            ->whereYear('date', $year)
            ->where('type', 'credit')
            ->whereNull('deleted_at')
            ->groupBy('month', 'year');

        $creditNotesData = $creditNotesQuery->get()->keyBy('month');

        // Build monthly data
        $months = [];
        $yearTotals = [
            'total_sales' => 0,
            'total_sale_tax' => 0,
            'total_returns' => 0,
            'total_return_tax' => 0,
            'net_sales' => 0,
            'net_sale_tax' => 0,
            'sales_profit' => 0,
            'returns_profit' => 0,
            'net_profit' => 0,
            'total_expenses' => 0,
            'total_salaries' => 0,
            'total_credit_notes' => 0,
            'final_net_profit' => 0,
        ];

        // Loop through all 12 months
        for ($month = 1; $month <= 12; $month++) {
            $sales = $salesData->get($month);
            $returns = $returnsData->get($month);
            $expenses = $expensesData->get($month);
            $salaries = $salariesData->get($month);
            $creditNotes = $creditNotesData->get($month);

            $totalSales = $sales ? (float)$sales->total_sales : 0.00;
            $totalSaleTax = $sales ? (float)$sales->total_sale_tax : 0.00;
            $salesProfit = $sales ? (float)$sales->total_profit : 0.00;
            $totalReturns = $returns ? (float)$returns->total_returns : 0.00;
            $totalReturnTax = $returns ? (float)$returns->total_return_tax : 0.00;
            $returnsProfitAmount = $returns ? (float)$returns->total_profit : 0.00;
            $totalExpenses = $expenses ? (float)$expenses->total_expenses : 0.00;
            $totalSalaries = $salaries ? (float)$salaries->total_salaries : 0.00;
            $totalCreditNotes = $creditNotes ? (float)$creditNotes->total_credit_notes : 0.00;

            // Calculate net values
            $netSales = $totalSales - $totalReturns;
            $netSaleTax = $totalSaleTax - $totalReturnTax;
            $netProfit = $salesProfit - $returnsProfitAmount;
            $finalNetProfit = $netProfit - $totalExpenses - $totalSalaries - $totalCreditNotes;

            $months[] = [
                'month' => $month,
                'month_name' => date('F', mktime(0, 0, 0, $month, 1)),
                'year' => $year,
                'sales' => round($totalSales, 2),
                'total_sale_tax' => round($totalSaleTax, 2),
                'returns' => round($totalReturns, 2),
                'total_return_tax' => round($totalReturnTax, 2),
                'net_sales' => round($netSales, 2),
                'net_sale_tax' => round($netSaleTax, 2),
                'sales_profit' => round($salesProfit, 2),
                'returns_profit' => round($returnsProfitAmount, 2),
                'net_profit' => round($netProfit, 2),
                'expenses' => round(-$totalExpenses, 2), // negative number
                'salaries' => round(-$totalSalaries, 2), // negative number
                'credit_notes' => round(-$totalCreditNotes, 2), // negative number
                'final_net_profit' => round($finalNetProfit, 2),
            ];

            // Add to year totals
            $yearTotals['total_sales'] += $totalSales;
            $yearTotals['total_sale_tax'] += $totalSaleTax;
            $yearTotals['total_returns'] += $totalReturns;
            $yearTotals['total_return_tax'] += $totalReturnTax;
            $yearTotals['net_sales'] += $netSales;
            $yearTotals['net_sale_tax'] += $netSaleTax;
            $yearTotals['sales_profit'] += $salesProfit;
            $yearTotals['returns_profit'] += $returnsProfitAmount;
            $yearTotals['net_profit'] += $netProfit;
            $yearTotals['total_expenses'] += $totalExpenses;
            $yearTotals['total_salaries'] += $totalSalaries;
            $yearTotals['total_credit_notes'] += $totalCreditNotes;
            $yearTotals['final_net_profit'] += $finalNetProfit;
        }

        // Round year totals
        $yearTotals = [
            'total_sales' => round($yearTotals['total_sales'], 2),
            'total_sale_tax' => round($yearTotals['total_sale_tax'], 2),
            'total_returns' => round($yearTotals['total_returns'], 2),
            'total_return_tax' => round($yearTotals['total_return_tax'], 2),
            'net_sales' => round($yearTotals['net_sales'], 2),
            'net_sale_tax' => round($yearTotals['net_sale_tax'], 2),
            'sales_profit' => round($yearTotals['sales_profit'], 2),
            'returns_profit' => round($yearTotals['returns_profit'], 2),
            'net_profit' => round($yearTotals['net_profit'], 2),
            'expenses' => round(-$yearTotals['total_expenses'], 2), // negative number
            'salaries' => round(-$yearTotals['total_salaries'], 2), // negative number
            'credit_notes' => round(-$yearTotals['total_credit_notes'], 2), // negative number
            'final_net_profit' => round($yearTotals['final_net_profit'], 2),
        ];

        return [
            'year' => $year,
            'months' => $months,
            'year_totals' => $yearTotals,
        ];
    }
}
