<?php

namespace App\Http\Controllers\Api\Employees;

use App\Helpers\RoleHelper;
use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Customers\CustomerPayment;
use App\Models\Customers\CustomerReturn;
use App\Models\Customers\Sale;
use App\Models\Employees\CommissionTargetRule;
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

        // Calculate commission data (optimized - uses shared method)
        $data = $this->calculateEmployeeCommissionData($employeeId, $month, $year);

        return ApiResponse::show('Monthly commission calculated successfully', $data);
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

        // Calculate commission data (optimized - uses shared method)
        $data = $this->calculateEmployeeCommissionData($employee->id, $month, $year);

        return ApiResponse::show('Monthly commission calculated successfully', $data);
    }

    /**
     * Get daily business data for an employee
     */
    private function getDailyBusinessData(int $employeeId, int $month, int $year, ?string $saleIncludeType = null, ?string $paymentIncludeType = null): array
    {
        // Default to 'Own' if not specified
        $saleIncludeType = $saleIncludeType ?? CommissionTargetRule::INCLUDE_TYPE_OWN;
        $paymentIncludeType = $paymentIncludeType ?? CommissionTargetRule::INCLUDE_TYPE_OWN;

        $firstDay = "{$year}-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01";
        $lastDay = date('Y-m-t', strtotime($firstDay));

        // Fetch sales based on sale include_type
        $salesQuery = Sale::query()
            ->approved()
            ->byDateRange($firstDay, $lastDay);

        if ($saleIncludeType === CommissionTargetRule::INCLUDE_TYPE_OWN) {
            $salesQuery->bySalesperson($employeeId);
        } elseif ($saleIncludeType === CommissionTargetRule::INCLUDE_TYPE_ALL_EXCEPT_OWN) {
            $salesQuery->whereHas('salesperson', function ($q) use ($employeeId) {
                $q->where('id', '!=', $employeeId);
            });
        }
        // For 'All', no employee filter needed

        $sales = $salesQuery
            ->selectRaw('DATE(date) as transaction_date, prefix, COUNT(*) as count, SUM(total_usd) as total')
            ->groupBy('transaction_date', 'prefix')
            ->get()
            ->groupBy('transaction_date');

        // Fetch payments based on payment include_type
        $paymentsQuery = CustomerPayment::query()
            ->approved()
            ->whereBetween('date', [$firstDay, $lastDay]);

        if ($paymentIncludeType === CommissionTargetRule::INCLUDE_TYPE_OWN) {
            $paymentsQuery->whereHas('customer', function ($q) use ($employeeId) {
                $q->where('salesperson_id', $employeeId);
            });
        } elseif ($paymentIncludeType === CommissionTargetRule::INCLUDE_TYPE_ALL_EXCEPT_OWN) {
            $paymentsQuery->whereHas('customer', function ($q) use ($employeeId) {
                $q->where('salesperson_id', '!=', $employeeId);
            });
        }
        // For 'All', no employee filter needed

        $payments = $paymentsQuery
            ->selectRaw('DATE(date) as transaction_date, prefix, COUNT(*) as count, SUM(amount_usd) as total')
            ->groupBy('transaction_date', 'prefix')
            ->get()
            ->groupBy('transaction_date');

        // Fetch returns based on sale include_type (returns follow sales)
        $returnsQuery = CustomerReturn::query()
            ->approved()
            ->received()
            ->whereBetween('date', [$firstDay, $lastDay]);

        if ($saleIncludeType === CommissionTargetRule::INCLUDE_TYPE_OWN) {
            $returnsQuery->whereHas('salesperson', function ($q) use ($employeeId) {
                $q->where('id', $employeeId);
            });
        } elseif ($saleIncludeType === CommissionTargetRule::INCLUDE_TYPE_ALL_EXCEPT_OWN) {
            $returnsQuery->whereHas('salesperson', function ($q) use ($employeeId) {
                $q->where('id', '!=', $employeeId);
            });
        }
        // For 'All', no employee filter needed

        $returns = $returnsQuery
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
            'base_salary' => $employee->base_salary,
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
     * Get business stats based on include_type
     * - 'Own': Only the employee's business
     * - 'All': All employees' business
     * - 'All except own': All employees except the given employee
     */
    private function getBusinessStatsByIncludeType(int $employeeId, int $month, int $year, string $includeType): array
    {
        $firstDay = "{$year}-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01";
        $lastDay = date('Y-m-t', strtotime($firstDay));

        // Get sales grouped by prefix based on include_type
        $salesQuery = Sale::query()
            ->approved()
            ->byDateRange($firstDay, $lastDay);

        if ($includeType === CommissionTargetRule::INCLUDE_TYPE_OWN) {
            $salesQuery->bySalesperson($employeeId);
        } elseif ($includeType === CommissionTargetRule::INCLUDE_TYPE_ALL_EXCEPT_OWN) {
            $salesQuery->whereHas('salesperson', function ($q) use ($employeeId) {
                $q->where('id', '!=', $employeeId);
            });
        }
        // For 'All', no employee filter needed

        $salesByPrefix = $salesQuery
            ->selectRaw('prefix, COUNT(*) as count, SUM(total_usd) as total')
            ->groupBy('prefix')
            ->get()
            ->keyBy('prefix');

        // Get payments grouped by prefix based on include_type
        $paymentsQuery = CustomerPayment::query()
            ->approved()
            ->whereBetween('date', [$firstDay, $lastDay]);

        if ($includeType === CommissionTargetRule::INCLUDE_TYPE_OWN) {
            $paymentsQuery->whereHas('customer', function ($q) use ($employeeId) {
                $q->where('salesperson_id', $employeeId);
            });
        } elseif ($includeType === CommissionTargetRule::INCLUDE_TYPE_ALL_EXCEPT_OWN) {
            $paymentsQuery->whereHas('customer', function ($q) use ($employeeId) {
                $q->where('salesperson_id', '!=', $employeeId);
            });
        }
        // For 'All', no employee filter needed

        $paymentsByPrefix = $paymentsQuery
            ->selectRaw('prefix, COUNT(*) as count, SUM(amount_usd) as total')
            ->groupBy('prefix')
            ->get()
            ->keyBy('prefix');

        // Get returns grouped by prefix based on include_type
        $returnsQuery = CustomerReturn::query()
            ->approved()
            ->received()
            ->whereBetween('date', [$firstDay, $lastDay]);

        if ($includeType === CommissionTargetRule::INCLUDE_TYPE_OWN) {
            $returnsQuery->whereHas('salesperson', function ($q) use ($employeeId) {
                $q->where('id', $employeeId);
            });
        } elseif ($includeType === CommissionTargetRule::INCLUDE_TYPE_ALL_EXCEPT_OWN) {
            $returnsQuery->whereHas('salesperson', function ($q) use ($employeeId) {
                $q->where('id', '!=', $employeeId);
            });
        }
        // For 'All', no employee filter needed

        $returnsByPrefix = $returnsQuery
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

        // Calculate after-tax totals (sum of after_tax_total from each prefix)
        $totalSales = array_sum(array_column($salesData, 'after_tax_total'));
        $totalPayments = array_sum(array_column($paymentsData, 'after_tax_total'));
        $totalReturns = array_sum(array_column($returnsData, 'after_tax_total'));

        return [
            'total_sales' => (float) $totalSales,
            'total_returns' => (float) $totalReturns,
            'total_payments' => (float) $totalPayments,
        ];
    }

    /**
     * Get sales and returns data based on include_type (optimized)
     */
    private function getSalesAndReturnsStats(int $employeeId, int $month, int $year, string $includeType): array
    {
        $firstDay = "{$year}-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01";
        $lastDay = date('Y-m-t', strtotime($firstDay));

        // Get sales based on include_type
        $salesQuery = Sale::query()
            ->approved()
            ->byDateRange($firstDay, $lastDay);

        if ($includeType === CommissionTargetRule::INCLUDE_TYPE_OWN) {
            $salesQuery->bySalesperson($employeeId);
        } elseif ($includeType === CommissionTargetRule::INCLUDE_TYPE_ALL_EXCEPT_OWN) {
            $salesQuery->whereHas('salesperson', function ($q) use ($employeeId) {
                $q->where('id', '!=', $employeeId);
            });
        }

        $salesByPrefix = $salesQuery
            ->selectRaw('prefix, COUNT(*) as count, SUM(total_usd) as total')
            ->groupBy('prefix')
            ->get()
            ->keyBy('prefix');

        // Get returns based on include_type (returns follow sales)
        $returnsQuery = CustomerReturn::query()
            ->approved()
            ->received()
            ->whereBetween('date', [$firstDay, $lastDay]);

        if ($includeType === CommissionTargetRule::INCLUDE_TYPE_OWN) {
            $returnsQuery->whereHas('salesperson', function ($q) use ($employeeId) {
                $q->where('id', $employeeId);
            });
        } elseif ($includeType === CommissionTargetRule::INCLUDE_TYPE_ALL_EXCEPT_OWN) {
            $returnsQuery->whereHas('salesperson', function ($q) use ($employeeId) {
                $q->where('id', '!=', $employeeId);
            });
        }

        $returnsByPrefix = $returnsQuery
            ->selectRaw('prefix, COUNT(*) as count, SUM(total_usd) as total')
            ->groupBy('prefix')
            ->get()
            ->keyBy('prefix');

        // Process sales data
        $salesData = [];
        foreach ([Sale::TAXPREFIX, Sale::TAXFREEPREFIX] as $prefix) {
            $salesData[$prefix] = ['count' => 0, 'total' => 0, 'after_tax_total' => 0];
        }
        foreach ($salesByPrefix as $prefix => $data) {
            $total = (float) $data->total;
            $afterTaxTotal = $prefix === Sale::TAXPREFIX ? $total / self::TAX_RATE : $total;
            $salesData[$prefix] = [
                'count' => (int) $data->count,
                'total' => $total,
                'after_tax_total' => $afterTaxTotal,
            ];
        }

        // Process returns data
        $returnsData = [];
        foreach ([CustomerReturn::TAXPREFIX, CustomerReturn::TAXFREEPREFIX] as $prefix) {
            $returnsData[$prefix] = ['count' => 0, 'total' => 0, 'after_tax_total' => 0];
        }
        foreach ($returnsByPrefix as $prefix => $data) {
            $total = (float) $data->total;
            $afterTaxTotal = $prefix === CustomerReturn::TAXPREFIX ? $total / self::TAX_RATE : $total;
            $returnsData[$prefix] = [
                'count' => (int) $data->count,
                'total' => $total,
                'after_tax_total' => $afterTaxTotal,
            ];
        }

        return [
            'total_sales' => (float) array_sum(array_column($salesData, 'after_tax_total')),
            'total_returns' => (float) array_sum(array_column($returnsData, 'after_tax_total')),
        ];
    }

    /**
     * Get payments data based on include_type (optimized)
     */
    private function getPaymentsStats(int $employeeId, int $month, int $year, string $includeType): array
    {
        $firstDay = "{$year}-" . str_pad($month, 2, '0', STR_PAD_LEFT) . "-01";
        $lastDay = date('Y-m-t', strtotime($firstDay));

        // Get payments based on include_type
        $paymentsQuery = CustomerPayment::query()
            ->approved()
            ->whereBetween('date', [$firstDay, $lastDay]);

        if ($includeType === CommissionTargetRule::INCLUDE_TYPE_OWN) {
            $paymentsQuery->whereHas('customer', function ($q) use ($employeeId) {
                $q->where('salesperson_id', $employeeId);
            });
        } elseif ($includeType === CommissionTargetRule::INCLUDE_TYPE_ALL_EXCEPT_OWN) {
            $paymentsQuery->whereHas('customer', function ($q) use ($employeeId) {
                $q->where('salesperson_id', '!=', $employeeId);
            });
        }

        $paymentsByPrefix = $paymentsQuery
            ->selectRaw('prefix, COUNT(*) as count, SUM(amount_usd) as total')
            ->groupBy('prefix')
            ->get()
            ->keyBy('prefix');

        // Process payments data
        $paymentsData = [];
        foreach ([CustomerPayment::TAXPREFIX, CustomerPayment::TAXFREEPREFIX] as $prefix) {
            $paymentsData[$prefix] = ['count' => 0, 'total' => 0, 'after_tax_total' => 0];
        }
        foreach ($paymentsByPrefix as $prefix => $data) {
            $total = (float) $data->total;
            $afterTaxTotal = $prefix === CustomerPayment::TAXPREFIX ? $total / self::TAX_RATE : $total;
            $paymentsData[$prefix] = [
                'count' => (int) $data->count,
                'total' => $total,
                'after_tax_total' => $afterTaxTotal,
            ];
        }

        return [
            'total_payments' => (float) array_sum(array_column($paymentsData, 'after_tax_total')),
        ];
    }

    /**
     * Shared method to calculate commission data for an employee (optimized)
     * This method is used by both getMonthlyCommission and getEmployeeMonthlyCommission
     */
    private function calculateEmployeeCommissionData(int $employeeId, int $month, int $year): array
    {
        // Get commission target for this employee
        $currentCommissionTarget = EmployeeCommissionTarget::with('commissionTarget.rules')
            ->byEmployee($employeeId)
            ->byMonth($month)
            ->byYear($year)
            ->first();

        // Determine include_type for sale and payment rules
        // Note: Fuel rules don't need their own data fetching - they use sale and payment data
        $saleIncludeType = CommissionTargetRule::INCLUDE_TYPE_OWN;
        $paymentIncludeType = CommissionTargetRule::INCLUDE_TYPE_OWN;

        if ($currentCommissionTarget && $currentCommissionTarget->commissionTarget) {
            foreach ($currentCommissionTarget->commissionTarget->rules as $rule) {
                if ($rule->type === 'sale') {
                    $saleIncludeType = $rule->include_type ?? CommissionTargetRule::INCLUDE_TYPE_OWN;
                } elseif ($rule->type === 'payment') {
                    $paymentIncludeType = $rule->include_type ?? CommissionTargetRule::INCLUDE_TYPE_OWN;
                }
                // Fuel rules are ignored - they use the sale and payment data
            }
        }

        // Fetch business data based on determined include_types (optimized)
        $totalSales = 0;
        $totalReturns = 0;
        $totalPayments = 0;

        // If both are 'Own', use existing method (no change needed)
        if ($saleIncludeType === CommissionTargetRule::INCLUDE_TYPE_OWN &&
            $paymentIncludeType === CommissionTargetRule::INCLUDE_TYPE_OWN) {
            $businessStats = $this->getBusinessStats($employeeId, $month, $year);
            $totalSales = $businessStats['total_sales'];
            $totalReturns = $businessStats['total_returns'];
            $totalPayments = $businessStats['total_payments'];
        } else {
            // Fetch sales and returns based on sale include_type (optimized - only one query)
            $salesStats = $this->getSalesAndReturnsStats($employeeId, $month, $year, $saleIncludeType);
            $totalSales = $salesStats['total_sales'];
            $totalReturns = $salesStats['total_returns'];

            // Fetch payments based on payment include_type (optimized - only one query)
            $paymentsStats = $this->getPaymentsStats($employeeId, $month, $year, $paymentIncludeType);
            $totalPayments = $paymentsStats['total_payments'];

            // Build business stats for display
            $employee = Employee::find($employeeId);
            $businessStats = [
                'employee_id' => $employee->id,
                'employee_name' => $employee->name ?? 'N/A',
                'base_salary' => $employee->base_salary,
                'month' => (int) $month,
                'year' => (int) $year,
                'VAT' => self::TAX_RATE,
                'total_sales' => $totalSales,
                'total_returns' => $totalReturns,
                'total_payments' => $totalPayments,
                'net_sales' => (float) ($totalSales - $totalReturns),
            ];
        }

        // Get daily business data based on include_types
        $dailyBusinessData = $this->getDailyBusinessData($employeeId, $month, $year, $saleIncludeType, $paymentIncludeType);

        // Calculate commissions using the fetched data
        $commissions = [];
        $totalCommission = 0;

        if ($currentCommissionTarget && $currentCommissionTarget->commissionTarget) {
            $rules = $currentCommissionTarget->commissionTarget->rules;

            foreach ($rules as $rule) {
                $commissionData = $this->calculateCommissionForRule(
                    $rule,
                    $totalSales,
                    $totalPayments,
                    $totalReturns
                );

                $commissions[] = $commissionData;
                $totalCommission += $commissionData['commission_amount'];
            }
        }

        return [
            'business_summary' => $businessStats,
            'daily_business' => $dailyBusinessData,
            'commission_target' => $currentCommissionTarget ? [
                'id' => $currentCommissionTarget->commissionTarget->id,
                'code' => $currentCommissionTarget->commissionTarget->prefix . $currentCommissionTarget->commissionTarget->code,
                'name' => $currentCommissionTarget->commissionTarget->name,
            ] : null,
            'commissions' => $commissions,
            'total_commission' => $totalCommission,
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

        // Calculate percent commission based on achievement and percent_type
        if ($rule->percent_type === CommissionTargetRule::PERCENTAGE_TYPE_FIXED) {
            // Fixed: Use the percent as-is
            $percentCommission = $rule->percent;
        } else {
            // Dynamic: Scale percent based on achievement
            $percentCommission = ($achievementPercent / 100) * $rule->percent;
        }

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
     * - Fixed percent_type: min(fuelAmount, max_amount) x percent%
     * - Dynamic percent_type:
     *   - Case 1: If (payment - returns + sales) / 2 < max_amount
     *             then: (((payment - returns + sales) / 2) / max_amount) x comm%
     *   - Case 2: If (payment - returns + sales) / 2 >= max_amount
     *             then: max_amount x comm%
     */
    private function calculateFuelCommission($rule, float $totalSales, float $totalPayments, float $totalReturns): float
    {
        $fuelAmount = ($totalPayments - $totalReturns + $totalSales) / 2;

        // If percent_type is 'fixed', use simple calculation with max_amount cap
        if ($rule->percent_type === CommissionTargetRule::PERCENTAGE_TYPE_FIXED) {
            $cappedAmount = min($fuelAmount, $rule->maximum_amount);
            return $cappedAmount * ($rule->percent / 100);
        }

        // Dynamic calculation (existing logic)
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
     * - Fixed percent_type: min(totalSales, max_amount) x percent%
     * - Dynamic percent_type:
     *   - Case 1: If sales < min_amount then: 0
     *   - Case 2: If sales >= min_amount then: min_amount x comm%
     */
    private function calculateSaleCommission($rule, float $totalSales): float
    {
        // If percent_type is 'fixed', use simple calculation with max_amount cap
        if ($rule->percent_type === CommissionTargetRule::PERCENTAGE_TYPE_FIXED) {
            $cappedAmount = min($totalSales, $rule->maximum_amount);
            return $cappedAmount * ($rule->percent / 100);
        }

        // Dynamic calculation (existing logic)
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
     * - Fixed percent_type: min(totalPayments, max_amount) x percent%
     * - Dynamic percent_type:
     *   - Case 1: If payment < max_amount then: (payment / max_amount) x comm%
     *   - Case 2: If payment >= max_amount then: max_amount x comm%
     */
    private function calculatePaymentCommission($rule, float $totalPayments): float
    {
        // If percent_type is 'fixed', use simple calculation with max_amount cap
        if ($rule->percent_type === CommissionTargetRule::PERCENTAGE_TYPE_FIXED) {
            $cappedAmount = min($totalPayments, $rule->maximum_amount);
            return $cappedAmount * ($rule->percent / 100);
        }

        // Dynamic calculation (existing logic)
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
