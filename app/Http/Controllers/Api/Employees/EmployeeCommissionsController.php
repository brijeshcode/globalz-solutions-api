<?php

namespace App\Http\Controllers\Api\Employees;

use App\Helpers\RoleHelper;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Customers\CustomerPayment;
use App\Models\Customers\CustomerReturn;
use App\Models\Customers\Sale;
use App\Models\Employees\Employee;
use App\Models\Employees\EmployeeCommissionTarget;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeCommissionsController extends Controller
{
    /**
     * Tax rate percentage (11%)
     */
    private const TAX_RATE = 1.11;

    public function getMonthlyCommission(Request $request): JsonResponse
    {
        // Only admins can access this endpoint
        if (!RoleHelper::canAdmin()) {
            return ApiResponse::customError('Only admins can access this endpoint', 403);
        }

        $request->validate([
            'employee_id' => 'required|integer|exists:employees,id',
            'month' => 'nullable|integer|min:1|max:12',
            'year' => 'nullable|integer|min:2000|max:2100',
        ]);

        $employeeId = $request->input('employee_id');
        $month = $request->input('month', date('m'));
        $year = $request->input('year', date('Y'));

        // Get commission target for this employee
        $currentCommissionTarget = EmployeeCommissionTarget::with('commissionTarget.rules')
            ->byEmployee($employeeId)
            ->byMonth($month)
            ->byYear($year)
            ->first();

        // Get business data summary
        $businessStats = $this->getBusinessStats($employeeId, $month, $year);

        // Get daily business data
        $dailyBusinessData = $this->getDailyBusinessData($employeeId, $month, $year);

        // Calculate commissions
        $commissions = [];
        $totalCommission = 0;

        if ($currentCommissionTarget && $currentCommissionTarget->commissionTarget) {
            $rules = $currentCommissionTarget->commissionTarget->rules;

            foreach ($rules as $rule) {
                $commissionData = $this->calculateCommissionForRule(
                    $rule,
                    $businessStats['total_sales'],
                    $businessStats['total_payments'],
                    $businessStats['total_returns']
                );

                $commissions[] = $commissionData;
                $totalCommission += $commissionData['commission_amount'];
            }
        }

        return ApiResponse::show(
            'Monthly commission calculated successfully',
            [
                'business_summary' => $businessStats,
                'daily_business' => $dailyBusinessData,
                'commission_target' => $currentCommissionTarget ? [
                    'id' => $currentCommissionTarget->commissionTarget->id,
                    'code' => $currentCommissionTarget->commissionTarget->prefix . $currentCommissionTarget->commissionTarget->code,
                    'name' => $currentCommissionTarget->commissionTarget->name,
                ] : null,
                'commissions' => $commissions,
                'total_commission' => $totalCommission,
            ]
        );
    }

    /**
     * Get monthly commission data for employee (salesman)
     * Employees can only view their own commission data
     */
    public function getEmployeeMonthlyCommission(Request $request): JsonResponse
    {
        // Only salesmen can access this endpoint
        if (!RoleHelper::isSalesman()) {
            return ApiResponse::customError('Only salesmen can access this endpoint', 403);
        }

        // Get the logged-in employee
        $employee = RoleHelper::getSalesmanEmployee();
        if (!$employee) {
            return ApiResponse::customError('Employee record not found for current user', 404);
        }

        // Validate month and year only (employee_id is auto-determined)
        $request->validate([
            'month' => 'nullable|integer|min:1|max:12',
            'year' => 'nullable|integer|min:2000|max:2100',
        ]);

        $month = $request->input('month', date('m'));
        $year = $request->input('year', date('Y'));

        // Get commission target for this employee
        $currentCommissionTarget = EmployeeCommissionTarget::with('commissionTarget.rules')
            ->byEmployee($employee->id)
            ->byMonth($month)
            ->byYear($year)
            ->first();

        // Get business data summary
        $businessStats = $this->getBusinessStats($employee->id, $month, $year);

        // Get daily business data
        $dailyBusinessData = $this->getDailyBusinessData($employee->id, $month, $year);

        // Calculate commissions
        $commissions = [];
        $totalCommission = 0;

        if ($currentCommissionTarget && $currentCommissionTarget->commissionTarget) {
            $rules = $currentCommissionTarget->commissionTarget->rules;

            foreach ($rules as $rule) {
                $commissionData = $this->calculateCommissionForRule(
                    $rule,
                    $businessStats['total_sales'],
                    $businessStats['total_payments'],
                    $businessStats['total_returns']
                );

                $commissions[] = $commissionData;
                $totalCommission += $commissionData['commission_amount'];
            }
        }

        return ApiResponse::show(
            'Monthly commission calculated successfully',
            [
                'business_summary' => $businessStats,
                'daily_business' => $dailyBusinessData,
                'commission_target' => $currentCommissionTarget ? [
                    'id' => $currentCommissionTarget->commissionTarget->id,
                    'code' => $currentCommissionTarget->commissionTarget->prefix . $currentCommissionTarget->commissionTarget->code,
                    'name' => $currentCommissionTarget->commissionTarget->name,
                ] : null,
                'commissions' => $commissions,
                'total_commission' => $totalCommission,
            ]
        );
    }

    /**
     * Get daily business data for an employee
     */
    private function getDailyBusinessData(int $employeeId, int $month, int $year): array
    {
        $firstDay = "{$year}-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01";
        $lastDay = date('Y-m-t', strtotime($firstDay));

        // Fetch approved sales by this employee in the date range, grouped by date and prefix
        $sales = Sale::query()
            ->approved()
            ->bySalesperson($employeeId)
            ->byDateRange($firstDay, $lastDay)
            ->selectRaw('DATE(date) as transaction_date, prefix, COUNT(*) as count, SUM(total_usd) as total')
            ->groupBy('transaction_date', 'prefix')
            ->get()
            ->groupBy('transaction_date');

        // Fetch approved payments for this employee's customers in the date range, grouped by date and prefix
        $payments = CustomerPayment::query()
            ->approved()
            ->whereHas('customer', function ($q) use ($employeeId) {
                $q->where('salesperson_id', $employeeId);
            })
            ->whereBetween('date', [$firstDay, $lastDay])
            ->selectRaw('DATE(date) as transaction_date, prefix, COUNT(*) as count, SUM(amount_usd) as total')
            ->groupBy('transaction_date', 'prefix')
            ->get()
            ->groupBy('transaction_date');

        // Fetch approved and received returns by this employee in the date range, grouped by date and prefix
        $returns = CustomerReturn::query()
            ->approved()
            ->received()
            ->whereHas('salesperson', function ($q) use ($employeeId) {
                $q->where('id', $employeeId);
            })
            ->whereBetween('date', [$firstDay, $lastDay])
            ->selectRaw('DATE(date) as transaction_date, prefix, COUNT(*) as count, SUM(total_usd) as total')
            ->groupBy('transaction_date', 'prefix')
            ->get()
            ->groupBy('transaction_date');

        // Always use default prefixes and merge with any found in data to ensure all prefixes are present
        $foundSalePrefixes = $sales->flatten()->pluck('prefix')->unique()->values()->all();
        $foundPaymentPrefixes = $payments->flatten()->pluck('prefix')->unique()->values()->all();
        $foundReturnPrefixes = $returns->flatten()->pluck('prefix')->unique()->values()->all();

        // Merge default prefixes with found prefixes to ensure both TAX and TAXFREE are always included
        $allSalePrefixes = array_unique(array_merge([Sale::TAXPREFIX, Sale::TAXFREEPREFIX], $foundSalePrefixes));
        $allPaymentPrefixes = array_unique(array_merge([CustomerPayment::TAXPREFIX, CustomerPayment::TAXFREEPREFIX], $foundPaymentPrefixes));
        $allReturnPrefixes = array_unique(array_merge([CustomerReturn::TAXPREFIX, CustomerReturn::TAXFREEPREFIX], $foundReturnPrefixes));

        // Generate daily data for all days in the month
        $daysInMonth = date('t', strtotime($firstDay));
        $dailyData = [];

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $date = "{$year}-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-" . str_pad($day, 2, '0', STR_PAD_LEFT);

            $salesByPrefix = [];
            $paymentsByPrefix = [];
            $returnsByPrefix = [];

            // Initialize all sales prefixes with zero
            foreach ($allSalePrefixes as $prefix) {
                $salesByPrefix[$prefix] = [
                    'count' => 0,
                    'total' => 0,
                ];
            }

            // Initialize all payment prefixes with zero
            foreach ($allPaymentPrefixes as $prefix) {
                $paymentsByPrefix[$prefix] = [
                    'count' => 0,
                    'total' => 0,
                ];
            }

            // Initialize all return prefixes with zero
            foreach ($allReturnPrefixes as $prefix) {
                $returnsByPrefix[$prefix] = [
                    'count' => 0,
                    'total' => 0,
                ];
            }

            // Update with actual sales data for this date
            if ($sales->has($date)) {
                foreach ($sales->get($date) as $sale) {
                    $salesByPrefix[$sale->prefix] = [
                        'count' => (int) $sale->count,
                        'total' => (float) $sale->total,
                    ];
                }
            }

            // Update with actual payment data for this date
            if ($payments->has($date)) {
                foreach ($payments->get($date) as $payment) {
                    $paymentsByPrefix[$payment->prefix] = [
                        'count' => (int) $payment->count,
                        'total' => (float) $payment->total,
                    ];
                }
            }

            // Update with actual return data for this date
            if ($returns->has($date)) {
                foreach ($returns->get($date) as $return) {
                    $returnsByPrefix[$return->prefix] = [
                        'count' => (int) $return->count,
                        'total' => (float) $return->total,
                    ];
                }
            }

            $dailyData[] = [
                'day' => $day,
                'date' => $date,
                'sales' => $salesByPrefix,
                'payments' => $paymentsByPrefix,
                'returns' => $returnsByPrefix,
            ];
        }

        return $dailyData;
    }

    /**
     * Get business stats only (without returning JsonResponse)
     */
    private function getBusinessStats(int $employeeId, int $month, int $year): array
    {
        $employee = Employee::find($employeeId);
        if (!$employee) {
            return [];
        }

        $firstDay = "{$year}-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01";
        $lastDay = date('Y-m-t', strtotime($firstDay));

        // Get sales grouped by prefix
        $salesByPrefix = Sale::query()
            ->approved()
            ->bySalesperson($employeeId)
            ->byDateRange($firstDay, $lastDay)
            ->selectRaw('prefix, COUNT(*) as count, SUM(total_usd) as total')
            ->groupBy('prefix')
            ->get()
            ->keyBy('prefix');

        // Get payments grouped by prefix
        $paymentsByPrefix = CustomerPayment::query()
            ->approved()
            ->whereHas('customer', function ($q) use ($employeeId) {
                $q->where('salesperson_id', $employeeId);
            })
            ->whereBetween('date', [$firstDay, $lastDay])
            ->selectRaw('prefix, COUNT(*) as count, SUM(amount_usd) as total')
            ->groupBy('prefix')
            ->get()
            ->keyBy('prefix');

        // Get returns grouped by prefix
        $returnsByPrefix = CustomerReturn::query()
            ->approved()
            ->received()
            ->whereHas('salesperson', function ($q) use ($employeeId) {
                $q->where('id', $employeeId);
            })
            ->whereBetween('date', [$firstDay, $lastDay])
            ->selectRaw('prefix, COUNT(*) as count, SUM(total_usd) as total')
            ->groupBy('prefix')
            ->get()
            ->keyBy('prefix');

        // Initialize sales with default prefixes
        $salesData = [];
        foreach ([Sale::TAXPREFIX, Sale::TAXFREEPREFIX] as $prefix) {
            $salesData[$prefix] = [
                'count' => 0,
                'total' => 0,
                'after_tax_total' => 0,
            ];
        }
        // Update with actual data
        foreach ($salesByPrefix as $prefix => $data) {
            $total = (float) $data->total;
            $afterTaxTotal = $prefix === Sale::TAXPREFIX ? $total / self::TAX_RATE : $total;
            $salesData[$prefix] = [
                'count' => (int) $data->count,
                'total' => $total,
                'after_tax_total' => $afterTaxTotal,
            ];
        }

        // Initialize payments with default prefixes
        $paymentsData = [];
        foreach ([CustomerPayment::TAXPREFIX, CustomerPayment::TAXFREEPREFIX] as $prefix) {
            $paymentsData[$prefix] = [
                'count' => 0,
                'total' => 0,
                'after_tax_total' => 0,
            ];
        }
        // Update with actual data
        foreach ($paymentsByPrefix as $prefix => $data) {
            $total = (float) $data->total;
            $afterTaxTotal = $prefix === CustomerPayment::TAXPREFIX ? $total / self::TAX_RATE : $total;
            $paymentsData[$prefix] = [
                'count' => (int) $data->count,
                'total' => $total,
                'after_tax_total' => $afterTaxTotal,
            ];
        }

        // Initialize returns with default prefixes
        $returnsData = [];
        foreach ([CustomerReturn::TAXPREFIX, CustomerReturn::TAXFREEPREFIX] as $prefix) {
            $returnsData[$prefix] = [
                'count' => 0,
                'total' => 0,
                'after_tax_total' => 0,
            ];
        }
        // Update with actual data
        foreach ($returnsByPrefix as $prefix => $data) {
            $total = (float) $data->total;
            $afterTaxTotal = $prefix === CustomerReturn::TAXPREFIX ? $total / self::TAX_RATE : $total;
            $returnsData[$prefix] = [
                'count' => (int) $data->count,
                'total' => $total,
                'after_tax_total' => $afterTaxTotal,
            ];
        }

        // Calculate subtotals (before tax deduction)
        $subtotalSales = array_sum(array_column($salesData, 'total'));
        $subtotalPayments = array_sum(array_column($paymentsData, 'total'));
        $subtotalReturns = array_sum(array_column($returnsData, 'total'));

        // Calculate after-tax totals (sum of after_tax_total from each prefix)
        $totalSales = array_sum(array_column($salesData, 'after_tax_total'));
        $totalPayments = array_sum(array_column($paymentsData, 'after_tax_total'));
        $totalReturns = array_sum(array_column($returnsData, 'after_tax_total'));

        return [
            'employee_id' => $employee->id,
            'employee_name' => $employee->name ?? 'N/A',
            'month' => (int) $month,
            'year' => (int) $year,
            'VAT' => self::TAX_RATE,
            'subtotal_sales' => (float) $subtotalSales,
            'subtotal_returns' => (float) $subtotalReturns,
            'subtotal_payments' => (float) $subtotalPayments,
            'total_sales' => (float) $totalSales,
            'total_returns' => (float) $totalReturns,
            'total_payments' => (float) $totalPayments,
            'net_sales' => (float) ($totalSales - $totalReturns),
            'sales_by_prefix' => $salesData,
            'payments_by_prefix' => $paymentsData,
            'returns_by_prefix' => $returnsData,
        ];
    }

    /**
     * Calculate commission for a given rule
     */
    private function calculateCommissionForRule($rule, float $totalSales, float $totalPayments, float $totalReturns): array
    {
        $commissionAmount = 0;
        $baseAmount = 0;
        $achievementPercent = 0;

        switch ($rule->type) {
            case 'fuel':
                $commissionAmount = $this->calculateFuelCommission($rule, $totalSales, $totalPayments, $totalReturns);
                $baseAmount = ($totalPayments - $totalReturns + $totalSales) / 2;
                $achievementPercent = $rule->maximum_amount > 0
                    ? ($baseAmount / $rule->maximum_amount) * 100
                    : 0;
                break;

            case 'sale':
                $commissionAmount = $this->calculateSaleCommission($rule, $totalSales);
                $baseAmount = $totalSales;
                $achievementPercent = $rule->minimum_amount < $totalSales
                    ? ($totalSales / $rule->minimum_amount) * 100
                    : 0;
                break;

            case 'payment':
                $commissionAmount = $this->calculatePaymentCommission($rule, $totalPayments);
                $baseAmount = $totalPayments;
                $achievementPercent = $rule->maximum_amount > 0
                    ? ($totalPayments / $rule->maximum_amount) * 100
                    : 0;
                break;
        }

        // Cap achievement percent at 100%
        $achievementPercent = min($achievementPercent, 100);

        // Calculate percent commission based on achievement
        $percentCommission = ($achievementPercent / 100) * $rule->percent;

        return [
            'rule_id' => $rule->id,
            'type' => $rule->type,
            'commission_label' => $rule->comission_label,
            'minimum_amount' => (float) $rule->minimum_amount,
            'maximum_amount' => (float) $rule->maximum_amount,
            'percent' => (float) $rule->percent,
            'percent_commission' => round($percentCommission, 2),
            'base_amount' => (float) $baseAmount,
            'commission_amount' => (float) $commissionAmount,
            'achievement_percent' => round($achievementPercent, 2),
        ];
    }

    /**
     * Calculate fuel type commission
     * Formula:
     * - Case 1: If (payment - returns + sales) / 2 < max_amount
     *           then: (((payment - returns + sales) / 2) / max_amount) x comm%
     * - Case 2: If (payment - returns + sales) / 2 >= max_amount
     *           then: max_amount x comm%
     */
    private function calculateFuelCommission($rule, float $totalSales, float $totalPayments, float $totalReturns): float
    {
        $fuelAmount = ($totalPayments - $totalReturns + $totalSales) / 2;

        if ($fuelAmount < $rule->maximum_amount) {
            // Case 1
            $dynamicPercent = ($fuelAmount / $rule->maximum_amount) * ($rule->percent);
            return  $fuelAmount * ($dynamicPercent / 100);
        } else {
            // Case 2
            return $rule->maximum_amount * ($rule->percent / 100);
        }
    }

    /**
     * Calculate sale type commission
     * Formula:
     * - Case 1: If sales < min_amount then: 0
     * - Case 2: If sales >= min_amount then: min_amount x comm%
     */
    private function calculateSaleCommission($rule, float $totalSales): float
    {
        if ($totalSales < $rule->minimum_amount) {
            // Case 1
            return 0;
        } else {
            // Case 2
            return $rule->minimum_amount * ($rule->percent / 100);
        }
    }

    /**
     * Calculate payment type commission
     * Formula:
     * - Case 1: If payment < max_amount then: (payment / max_amount) x comm%
     * - Case 2: If payment >= max_amount then: max_amount x comm%
     */
    private function calculatePaymentCommission($rule, float $totalPayments): float
    {
        if ($totalPayments < $rule->maximum_amount) {
            // Case 1
            $dynamicPercent = ($totalPayments / $rule->maximum_amount) * $rule->percent;
            return ($dynamicPercent / 100) * $totalPayments;
        } else {
            // Case 2
            return $rule->maximum_amount * ($rule->percent / 100);
        }
    }
}
