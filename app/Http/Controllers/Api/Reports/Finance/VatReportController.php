<?php

namespace App\Http\Controllers\Api\Reports\Finance;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Customers\CustomerReturn;
use App\Models\Customers\CustomerReturnItem;
use App\Models\Customers\Sale;
use App\Models\Expenses\ExpenseTransaction;
use App\Models\Suppliers\Purchase;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VatReportController extends Controller
{

    public function index(Request $request): JsonResponse
    {
        [$fromDate, $toDate] = $this->resolveDateRange($request);

        $report = $this->calculateVatReport($fromDate, $toDate);

        return ApiResponse::send('VAT report retrieved successfully', 200, [
            'from_date' => $fromDate,
            'to_date'   => $toDate,
            ...$report,
        ]);
    }

    private function resolveDateRange(Request $request): array
    {
        $fromDate = $request->get('from_date');
        $toDate   = $request->get('to_date');

        // from_date / to_date takes priority — ignore year/quarter if provided
        if ($fromDate || $toDate) {
            return [$fromDate, $toDate];
        }

        $year    = $request->get('year');
        $quarter = $request->get('quarter'); // 1, 2, 3, or 4

        if ($year && $quarter) {
            $quarterMap = [
                1 => ['01-01', '03-31'],
                2 => ['04-01', '06-30'],
                3 => ['07-01', '09-30'],
                4 => ['10-01', '12-31'],
            ];
            return [
                $year . '-' . $quarterMap[$quarter][0],
                $year . '-' . $quarterMap[$quarter][1],
            ];
        }

        if ($year) {
            return [$year . '-01-01', $year . '-12-31'];
        }

        // default: current quarter
        return [
            Carbon::now()->startOfQuarter()->format('Y-m-d'),
            Carbon::now()->endOfQuarter()->format('Y-m-d'),
        ];
    }

    public function calculateVatReport(?string $fromDate, ?string $toDate): array
    {
        // total sales & returns
        $totalSales = (float) Sale::where('prefix', Sale::TAXPREFIX)
            ->approved()
            ->when($fromDate, fn($q) => $q->where('date', '>=', $fromDate))
            ->when($toDate,   fn($q) => $q->where('date', '<=', $toDate))
            ->sum('total_usd');

        $totalReturns = (float) CustomerReturn::where('prefix', CustomerReturn::TAXPREFIX)
            ->approved()
            ->when($fromDate, fn($q) => $q->where('date', '>=', $fromDate))
            ->when($toDate,   fn($q) => $q->where('date', '<=', $toDate))
            ->sum('total_usd');

        $netSales = $totalSales - $totalReturns;

        // vat collected on approved sales
        $vatSalesTotal = (float) Sale::where('prefix', Sale::TAXPREFIX)
            ->approved()
            ->when($fromDate, fn($q) => $q->where('date', '>=', $fromDate))
            ->when($toDate,   fn($q) => $q->where('date', '<=', $toDate))
            ->sum('total_tax_amount_usd');

        // vat returned to customers on approved returns
        $vatReturnTotal = (float) (CustomerReturnItem::whereHas('customerReturn', function ($q) use ($fromDate, $toDate) {
            $q->where('prefix', CustomerReturn::TAXPREFIX)
              ->approved()
              ->when($fromDate, fn($q) => $q->where('date', '>=', $fromDate))
              ->when($toDate,   fn($q) => $q->where('date', '<=', $toDate));
        })->selectRaw('SUM(tax_amount_usd * quantity) as total')
            ->value('total') ?? 0);

        // net vat on sales (collected - returned)
        $netVatSales = $vatSalesTotal - $vatReturnTotal;

        // vat paid via expense category (is_vat_category)
        $vatExpenseTotal = (float) ExpenseTransaction::whereHas('expenseCategory', function ($q) {
            $q->where('is_vat_category', true);
        })
            ->when($fromDate, fn($q) => $q->where('date', '>=', $fromDate))
            ->when($toDate,   fn($q) => $q->where('date', '<=', $toDate))
            ->sum('amount_usd');

        // vat amount on all expense transactions
        $expenseVatTotal = (float) ExpenseTransaction::query()
            ->when($fromDate, fn($q) => $q->where('date', '>=', $fromDate))
            ->when($toDate,   fn($q) => $q->where('date', '<=', $toDate))
            ->sum('vat_amount_usd');

        // vat paid via purchase (delivered TAX purchases)
        $vatPurchaseTotal = (float) Purchase::where('prefix', Purchase::TAXPREFIX)
            ->where('status', 'Delivered')
            ->when($fromDate, fn($q) => $q->where('date', '>=', $fromDate))
            ->when($toDate,   fn($q) => $q->where('date', '<=', $toDate))
            ->sum('tax_usd');

        // vat difference (net vat collected - all vat paid)
        $vatDifference = $netVatSales - $vatExpenseTotal - $expenseVatTotal - $vatPurchaseTotal;

        return [
            'total_sales'        => round($totalSales, 2),
            'total_returns'      => round($totalReturns, 2),
            'net_sales'          => round($netSales, 2),
            'vat_sales_total'    => round($vatSalesTotal, 2),
            'vat_return_total'   => round($vatReturnTotal, 2),
            'net_vat_sales'      => round($netVatSales, 2),
            'vat_expense_total'  => round($vatExpenseTotal, 2),
            'expense_vat_total'  => round($expenseVatTotal, 2),
            'vat_purchase_total' => round($vatPurchaseTotal, 2),
            'vat_difference'     => round($vatDifference, 2),
        ];
    }
}
