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
        // Get sales data (approved sales only)
        $salesQuery = DB::table('sales')
            ->selectRaw('
                MONTH(date) as month,
                YEAR(date) as year,
                SUM(total_usd) as total_sales,
                SUM(total_profit) as total_profit
            ')
            ->whereYear('date', $year)
            ->whereNull('deleted_at')
            ->whereNotNull('approved_by')
            ->groupBy('month', 'year');

        $salesData = $salesQuery->get()->keyBy('month');

        // Get returns data (approved and received returns only)
        $returnsQuery = DB::table('customer_returns')
            ->selectRaw('
                MONTH(date) as month,
                YEAR(date) as year,
                SUM(total_usd) as total_returns
            ')
            ->whereYear('date', $year)
            ->whereNull('deleted_at')
            ->whereNotNull('approved_by')
            ->whereNotNull('return_received_by')
            ->groupBy('month', 'year');

        $returnsData = $returnsQuery->get()->keyBy('month');

        // Get returns profit (from customer_return_items - only for received returns)
        $returnsProfitQuery = DB::table('customer_return_items')
            ->join('customer_returns', 'customer_return_items.customer_return_id', '=', 'customer_returns.id')
            ->selectRaw('
                MONTH(customer_returns.date) as month,
                YEAR(customer_returns.date) as year,
                SUM(customer_return_items.total_profit) as total_profit
            ')
            ->whereYear('customer_returns.date', $year)
            ->whereNull('customer_returns.deleted_at')
            ->whereNull('customer_return_items.deleted_at')
            ->whereNotNull('customer_returns.return_received_by')
            ->groupBy('month', 'year');

        $returnsProfitData = $returnsProfitQuery->get()->keyBy('month');

        // Get expenses data
        $expensesQuery = DB::table('expense_transactions')
            ->selectRaw('
                MONTH(date) as month,
                YEAR(date) as year,
                SUM(amount_usd) as total_expenses
            ')
            ->whereYear('date', $year)
            ->whereNull('deleted_at')
            ->groupBy('month', 'year');

        $expensesData = $expensesQuery->get()->keyBy('month');

        // Get salaries data (by month and year fields)
        $salariesQuery = DB::table('salaries')
            ->selectRaw('
                month,
                year,
                SUM(final_total) as total_salaries
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
            'total_returns' => 0,
            'net_sales' => 0,
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
            $returnsProfit = $returnsProfitData->get($month);
            $expenses = $expensesData->get($month);
            $salaries = $salariesData->get($month);
            $creditNotes = $creditNotesData->get($month);

            $totalSales = $sales ? (float)$sales->total_sales : 0.00;
            $salesProfit = $sales ? (float)$sales->total_profit : 0.00;
            $totalReturns = $returns ? (float)$returns->total_returns : 0.00;
            $returnsProfitAmount = $returnsProfit ? (float)$returnsProfit->total_profit : 0.00;
            $totalExpenses = $expenses ? (float)$expenses->total_expenses : 0.00;
            $totalSalaries = $salaries ? (float)$salaries->total_salaries : 0.00;
            $totalCreditNotes = $creditNotes ? (float)$creditNotes->total_credit_notes : 0.00;

            // Calculate net values
            $netSales = $totalSales - $totalReturns;
            $netProfit = $salesProfit - $returnsProfitAmount;
            $finalNetProfit = $netProfit - $totalExpenses - $totalSalaries - $totalCreditNotes;

            $months[] = [
                'month' => $month,
                'month_name' => date('F', mktime(0, 0, 0, $month, 1)),
                'year' => $year,
                'sales' => round($totalSales, 2),
                'returns' => round($totalReturns, 2),
                'net_sales' => round($netSales, 2),
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
            $yearTotals['total_returns'] += $totalReturns;
            $yearTotals['net_sales'] += $netSales;
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
            'total_returns' => round($yearTotals['total_returns'], 2),
            'net_sales' => round($yearTotals['net_sales'], 2),
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
