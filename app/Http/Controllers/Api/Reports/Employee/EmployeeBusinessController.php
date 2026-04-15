<?php

namespace App\Http\Controllers\Api\Reports\Employee;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeBusinessController extends Controller
{
    public function monthly(Request $request): JsonResponse
    {
        $year  = (int) $request->get('year',  now()->year);
        $month = (int) $request->get('month', now()->month);

        $data = $this->getMonthlyData($year, $month);

        return ApiResponse::send('Monthly employee business report retrieved successfully', 200, $data);
    }

    public function index(Request $request): JsonResponse
    {
        $year = $request->get('year', now()->year);

        $reportData = $this->getEmployeeBusinessData($year);

        return ApiResponse::send('Employee business report retrieved successfully', 200, $reportData);
    }

    private function getMonthlyData(int $year, int $month): array
    {
        // Sales: INV (tax), INX (tax-free)
        $salesData = DB::table('sales')
            ->join('employees', 'sales.salesperson_id', '=', 'employees.id')
            ->selectRaw("
                employees.id as employee_id,
                employees.name as employee_name,
                SUM(CASE WHEN sales.prefix = 'INV' THEN sales.total_usd ELSE 0 END) as tax,
                SUM(CASE WHEN sales.prefix = 'INX' THEN sales.total_usd ELSE 0 END) as tax_free
            ")
            ->whereYear('sales.date', $year)
            ->whereMonth('sales.date', $month)
            ->whereNull('sales.deleted_at')
            ->whereNotNull('sales.approved_by')
            ->groupBy('employees.id', 'employees.name')
            ->get()
            ->keyBy('employee_id');

        // Payments: RCT (tax), RCX (tax-free)
        $paymentsData = DB::table('customer_payments')
            ->join('customers', 'customer_payments.customer_id', '=', 'customers.id')
            ->join('employees', 'customers.salesperson_id', '=', 'employees.id')
            ->selectRaw("
                employees.id as employee_id,
                employees.name as employee_name,
                SUM(CASE WHEN customer_payments.prefix = 'RCT' THEN customer_payments.amount_usd ELSE 0 END) as tax,
                SUM(CASE WHEN customer_payments.prefix = 'RCX' THEN customer_payments.amount_usd ELSE 0 END) as tax_free
            ")
            ->whereYear('customer_payments.date', $year)
            ->whereMonth('customer_payments.date', $month)
            ->whereNull('customer_payments.deleted_at')
            ->whereNotNull('customer_payments.approved_by')
            ->groupBy('employees.id', 'employees.name')
            ->get()
            ->keyBy('employee_id');

        // Returns: RTN (tax), RTX (tax-free)
        $returnsData = DB::table('customer_returns')
            ->join('employees', 'customer_returns.salesperson_id', '=', 'employees.id')
            ->selectRaw("
                employees.id as employee_id,
                employees.name as employee_name,
                SUM(CASE WHEN customer_returns.prefix = 'RTN' THEN customer_returns.total_usd ELSE 0 END) as tax,
                SUM(CASE WHEN customer_returns.prefix = 'RTX' THEN customer_returns.total_usd ELSE 0 END) as tax_free
            ")
            ->whereYear('customer_returns.date', $year)
            ->whereMonth('customer_returns.date', $month)
            ->whereNull('customer_returns.deleted_at')
            ->whereNotNull('customer_returns.return_received_by')
            ->groupBy('employees.id', 'employees.name')
            ->get()
            ->keyBy('employee_id');

        // Credit notes: CRN (tax), CRX (tax-free) — credit type only, no debit
        $creditData = DB::table('customer_credit_debit_notes')
            ->join('customers', 'customer_credit_debit_notes.customer_id', '=', 'customers.id')
            ->join('employees', 'customers.salesperson_id', '=', 'employees.id')
            ->selectRaw("
                employees.id as employee_id,
                employees.name as employee_name,
                SUM(CASE WHEN customer_credit_debit_notes.prefix = 'CRN' THEN customer_credit_debit_notes.amount_usd ELSE 0 END) as tax,
                SUM(CASE WHEN customer_credit_debit_notes.prefix = 'CRX' THEN customer_credit_debit_notes.amount_usd ELSE 0 END) as tax_free
            ")
            ->whereYear('customer_credit_debit_notes.date', $year)
            ->whereMonth('customer_credit_debit_notes.date', $month)
            ->whereNull('customer_credit_debit_notes.deleted_at')
            ->where('customer_credit_debit_notes.type', 'credit')
            ->groupBy('employees.id', 'employees.name')
            ->get()
            ->keyBy('employee_id');

        $employeeIds = collect()
            ->merge($salesData->keys())
            ->merge($paymentsData->keys())
            ->merge($returnsData->keys())
            ->merge($creditData->keys())
            ->unique()
            ->sort()
            ->values();

        $employees   = [];
        $grandTotals = $this->emptyMonthlyTotals();

        foreach ($employeeIds as $empId) {
            $saleRow   = $this->toMonthlyRow($salesData->get($empId),   'INV', 'INX');
            $payRow    = $this->toMonthlyRow($paymentsData->get($empId), 'RCT', 'RCX');
            $retRow    = $this->toMonthlyRow($returnsData->get($empId),  'RTN', 'RTX');
            $creditRow = $this->toMonthlyRow($creditData->get($empId),   'CRN', 'CRX');

            $balTax     = round(($payRow['tax']      + $retRow['tax']      + $creditRow['tax'])      - $saleRow['tax'],      2);
            $balTaxFree = round(($payRow['tax_free'] + $retRow['tax_free'] + $creditRow['tax_free']) - $saleRow['tax_free'], 2);
            $balRow = [
                'tax'      => $balTax,
                'tax_free' => $balTaxFree,
                'total'    => round($balTax + $balTaxFree, 2),
            ];

            $name = $salesData->get($empId)?->employee_name
                ?? $paymentsData->get($empId)?->employee_name
                ?? $returnsData->get($empId)?->employee_name
                ?? $creditData->get($empId)?->employee_name;

            $employees[] = [
                'employee_id'   => $empId,
                'employee_name' => $name,
                'sale'          => $saleRow,
                'payment'       => $payRow,
                'returns'       => $retRow,
                'credit_note'   => $creditRow,
                'balance'       => $balRow,
            ];

            foreach (['sale' => $saleRow, 'payment' => $payRow, 'returns' => $retRow, 'credit_note' => $creditRow, 'balance' => $balRow] as $metric => $row) {
                $grandTotals[$metric]['tax']      += $row['tax'];
                $grandTotals[$metric]['tax_free'] += $row['tax_free'];
                $grandTotals[$metric]['total']    += $row['total'];
            }
        }

        foreach ($grandTotals as &$metric) {
            $metric = array_map(fn($v) => round($v, 2), $metric);
        }
        unset($metric);

        return [
            'year'         => $year,
            'month'        => $month,
            'month_name'   => date('F', mktime(0, 0, 0, $month, 1)),
            'employees'    => $employees,
            'grand_totals' => $grandTotals,
        ];
    }

    private function toMonthlyRow(?object $row, string $taxPrefix = '', string $taxFreePrefix = ''): array
    {
        $tax      = $row ? (float) $row->tax      : 0.0;
        $tax_free = $row ? (float) $row->tax_free : 0.0;

        return [
            'tax_prefix'      => $taxPrefix,
            'tax'             => round($tax, 2),
            'tax_free_prefix' => $taxFreePrefix,
            'tax_free'        => round($tax_free, 2),
            'total'           => round($tax + $tax_free, 2),
        ];
    }

    private function emptyMonthlyTotals(): array
    {
        $zero = ['tax' => 0.0, 'tax_free' => 0.0, 'total' => 0.0];

        return [
            'sale'        => $zero,
            'payment'     => $zero,
            'returns'     => $zero,
            'credit_note' => $zero,
            'balance'     => $zero,
        ];
    }

    private function getEmployeeBusinessData(int $year): array
    {
        // Get sales data by employee - only approved sales, exclude tax
        $salesQuery = DB::table('sales')
            ->join('employees', 'sales.salesperson_id', '=', 'employees.id')
            ->selectRaw('
                employees.id as employee_id,
                employees.name as employee_name,
                MONTH(sales.date) as month,
                YEAR(sales.date) as year,
                SUM(sales.total_usd - sales.total_tax_amount_usd) as total_sales,
                SUM(sales.total_tax_amount_usd) as total_sale_tax
            ')
            ->whereYear('sales.date', $year)
            ->whereNull('sales.deleted_at')
            ->whereNotNull('sales.approved_by')
            ->groupBy('employee_id', 'employee_name', 'month', 'year')
            ->orderBy('employee_name')
            ->orderBy('month');

        $salesData = $salesQuery->get()->keyBy(function ($item) {
            return $item->employee_id . '_' . $item->month;
        });

        // Get payments data by employee (through customer's salesperson) - only approved payments
        $paymentsQuery = DB::table('customer_payments')
            ->join('customers', 'customer_payments.customer_id', '=', 'customers.id')
            ->join('employees', 'customers.salesperson_id', '=', 'employees.id')
            ->selectRaw("
                employees.id as employee_id,
                employees.name as employee_name,
                MONTH(customer_payments.date) as month,
                YEAR(customer_payments.date) as year,
                SUM(CASE WHEN customer_payments.prefix = 'RCX' THEN customer_payments.amount_usd ELSE 0 END) as total_payments_tax_free,
                SUM(CASE WHEN customer_payments.prefix = 'RCT' THEN customer_payments.amount_usd ELSE 0 END) as total_payments_with_tax,
                SUM(customer_payments.amount_usd) as total_payments
            ")
            ->whereYear('customer_payments.date', $year)
            ->whereNull('customer_payments.deleted_at')
            ->whereNotNull('customer_payments.approved_by')
            ->groupBy('employee_id', 'employee_name', 'month', 'year');

        $paymentsData = $paymentsQuery->get()->keyBy(function ($item) {
            return $item->employee_id . '_' . $item->month;
        });

        // Get returns data by employee - only received returns, exclude tax
        $returnsQuery = DB::table('customer_return_items')
            ->join('customer_returns', 'customer_return_items.customer_return_id', '=', 'customer_returns.id')
            ->join('employees', 'customer_returns.salesperson_id', '=', 'employees.id')
            ->selectRaw('
                employees.id as employee_id,
                employees.name as employee_name,
                MONTH(customer_returns.date) as month,
                YEAR(customer_returns.date) as year,
                SUM(customer_return_items.total_price_usd - (customer_return_items.tax_amount_usd * customer_return_items.quantity)) as total_returns,
                SUM(customer_return_items.tax_amount_usd * customer_return_items.quantity) as total_return_tax
            ')
            ->whereYear('customer_returns.date', $year)
            ->whereNull('customer_returns.deleted_at')
            ->whereNull('customer_return_items.deleted_at')
            ->whereNotNull('customer_returns.return_received_by')
            ->groupBy('employee_id', 'employee_name', 'month', 'year');

        $returnsData = $returnsQuery->get()->keyBy(function ($item) {
            return $item->employee_id . '_' . $item->month;
        });

        // Get all unique employee IDs from all datasets
        $employeeIds = collect()
            ->merge($salesData->pluck('employee_id'))
            ->merge($paymentsData->pluck('employee_id'))
            ->merge($returnsData->pluck('employee_id'))
            ->unique()
            ->sort()
            ->values();

        // Build employee data structure
        $employeesData = [];
        $yearTotals = [
            'total_sales' => 0,
            'total_sale_tax' => 0,
            'total_payments_tax_free' => 0,
            'total_payments_with_tax' => 0,
            'total_payments' => 0,
            'total_returns' => 0,
            'total_return_tax' => 0,
        ];

        foreach ($employeeIds as $employeeId) {
            // Get employee name from any of the datasets
            $employeeName = $salesData->firstWhere('employee_id', $employeeId)?->employee_name
                ?? $paymentsData->firstWhere('employee_id', $employeeId)?->employee_name
                ?? $returnsData->firstWhere('employee_id', $employeeId)?->employee_name;

            $months = [];
            $employeeTotals = [
                'total_sales' => 0,
                'total_sale_tax' => 0,
                'total_payments_tax_free' => 0,
                'total_payments_with_tax' => 0,
                'total_payments' => 0,
                'total_returns' => 0,
                'total_return_tax' => 0,
            ];

            // Loop through all 12 months
            for ($month = 1; $month <= 12; $month++) {
                $key = $employeeId . '_' . $month;

                $sales = $salesData->get($key);
                $payments = $paymentsData->get($key);
                $returns = $returnsData->get($key);

                $totalSales = $sales ? (float)$sales->total_sales : 0.00;
                $totalSaleTax = $sales ? (float)$sales->total_sale_tax : 0.00;
                $totalPaymentsTaxFree = $payments ? (float)$payments->total_payments_tax_free : 0.00;
                $totalPaymentsWithTax = $payments ? (float)$payments->total_payments_with_tax : 0.00;
                $totalPayments = $payments ? (float)$payments->total_payments : 0.00;
                $totalReturns = $returns ? (float)$returns->total_returns : 0.00;
                $totalReturnTax = $returns ? (float)$returns->total_return_tax : 0.00;

                $months[] = [
                    'month' => $month,
                    'month_name' => date('F', mktime(0, 0, 0, $month, 1)),
                    'year' => $year,
                    'total_sales' => round($totalSales, 2),
                    'total_sale_tax' => round($totalSaleTax, 2),
                    'total_payments_tax_free' => round($totalPaymentsTaxFree, 2),
                    'total_payments_with_tax' => round($totalPaymentsWithTax, 2),
                    'total_payments' => round($totalPayments, 2),
                    'total_returns' => round($totalReturns, 2),
                    'total_return_tax' => round($totalReturnTax, 2),
                ];

                // Add to employee totals
                $employeeTotals['total_sales'] += $totalSales;
                $employeeTotals['total_sale_tax'] += $totalSaleTax;
                $employeeTotals['total_payments_tax_free'] += $totalPaymentsTaxFree;
                $employeeTotals['total_payments_with_tax'] += $totalPaymentsWithTax;
                $employeeTotals['total_payments'] += $totalPayments;
                $employeeTotals['total_returns'] += $totalReturns;
                $employeeTotals['total_return_tax'] += $totalReturnTax;
            }

            // Round employee totals
            $employeeTotals = array_map(function($value) {
                return round($value, 2);
            }, $employeeTotals);

            $employeesData[] = [
                'employee_id' => $employeeId,
                'employee_name' => $employeeName,
                'months' => $months,
                'totals' => $employeeTotals,
            ];

            // Add to year totals
            $yearTotals['total_sales'] += $employeeTotals['total_sales'];
            $yearTotals['total_sale_tax'] += $employeeTotals['total_sale_tax'];
            $yearTotals['total_payments_tax_free'] += $employeeTotals['total_payments_tax_free'];
            $yearTotals['total_payments_with_tax'] += $employeeTotals['total_payments_with_tax'];
            $yearTotals['total_payments'] += $employeeTotals['total_payments'];
            $yearTotals['total_returns'] += $employeeTotals['total_returns'];
            $yearTotals['total_return_tax'] += $employeeTotals['total_return_tax'];
        }

        // Round year totals
        $yearTotals = array_map(function($value) {
            return round($value, 2);
        }, $yearTotals);

        return [
            'employees' => $employeesData,
            'year_totals' => $yearTotals,
        ];
    }
}
