<?php

namespace App\Http\Controllers\Api\Reports\Employee;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeBusinessController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $year = $request->get('year', now()->year);

        $reportData = $this->getEmployeeBusinessData($year);

        return ApiResponse::send('Employee business report retrieved successfully', 200, $reportData);
    }

    private function getEmployeeBusinessData(int $year): array
    {
        // Get sales data by employee - only approved sales
        $salesQuery = DB::table('sales')
            ->join('employees', 'sales.salesperson_id', '=', 'employees.id')
            ->selectRaw('
                employees.id as employee_id,
                employees.name as employee_name,
                MONTH(sales.date) as month,
                YEAR(sales.date) as year,
                SUM(sales.total_usd) as total_sales
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
            ->selectRaw('
                employees.id as employee_id,
                employees.name as employee_name,
                MONTH(customer_payments.date) as month,
                YEAR(customer_payments.date) as year,
                SUM(customer_payments.amount_usd) as total_payments
            ')
            ->whereYear('customer_payments.date', $year)
            ->whereNull('customer_payments.deleted_at')
            ->whereNotNull('customer_payments.approved_by')
            ->groupBy('employee_id', 'employee_name', 'month', 'year');

        $paymentsData = $paymentsQuery->get()->keyBy(function ($item) {
            return $item->employee_id . '_' . $item->month;
        });

        // Get returns data by employee - only received returns
        $returnsQuery = DB::table('customer_returns')
            ->join('employees', 'customer_returns.salesperson_id', '=', 'employees.id')
            ->selectRaw('
                employees.id as employee_id,
                employees.name as employee_name,
                MONTH(customer_returns.date) as month,
                YEAR(customer_returns.date) as year,
                SUM(customer_returns.total_usd) as total_returns
            ')
            ->whereYear('customer_returns.date', $year)
            ->whereNull('customer_returns.deleted_at')
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
            'total_payments' => 0,
            'total_returns' => 0,
        ];

        foreach ($employeeIds as $employeeId) {
            // Get employee name from any of the datasets
            $employeeName = $salesData->firstWhere('employee_id', $employeeId)?->employee_name
                ?? $paymentsData->firstWhere('employee_id', $employeeId)?->employee_name
                ?? $returnsData->firstWhere('employee_id', $employeeId)?->employee_name;

            $months = [];
            $employeeTotals = [
                'total_sales' => 0,
                'total_payments' => 0,
                'total_returns' => 0,
            ];

            // Loop through all 12 months
            for ($month = 1; $month <= 12; $month++) {
                $key = $employeeId . '_' . $month;

                $sales = $salesData->get($key);
                $payments = $paymentsData->get($key);
                $returns = $returnsData->get($key);

                $totalSales = $sales ? (float)$sales->total_sales : 0.00;
                $totalPayments = $payments ? (float)$payments->total_payments : 0.00;
                $totalReturns = $returns ? (float)$returns->total_returns : 0.00;

                $months[] = [
                    'month' => $month,
                    'month_name' => date('F', mktime(0, 0, 0, $month, 1)),
                    'year' => $year,
                    'total_sales' => round($totalSales, 2),
                    'total_payments' => round($totalPayments, 2),
                    'total_returns' => round($totalReturns, 2),
                ];

                // Add to employee totals
                $employeeTotals['total_sales'] += $totalSales;
                $employeeTotals['total_payments'] += $totalPayments;
                $employeeTotals['total_returns'] += $totalReturns;
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
            $yearTotals['total_payments'] += $employeeTotals['total_payments'];
            $yearTotals['total_returns'] += $employeeTotals['total_returns'];
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
