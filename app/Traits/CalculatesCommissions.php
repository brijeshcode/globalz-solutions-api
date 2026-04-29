<?php

namespace App\Traits;

use App\Models\Customers\CustomerPayment;
use App\Models\Customers\CustomerReturn;
use App\Models\Customers\Sale;
use App\Models\Employees\CommissionTargetRule;
use Carbon\Carbon;

trait CalculatesCommissions
{
    protected function commissionTaxRate(): float
    {
        return 1.11;
    }

    protected function getCommissionTotals(
        int $employeeId,
        Carbon $firstDay,
        Carbon $lastDay,
        string $saleIncludeType,
        string $paymentIncludeType
    ): array {
        $salesQuery = Sale::query()->approved()->byDateRange($firstDay, $lastDay);
        if ($saleIncludeType === CommissionTargetRule::INCLUDE_TYPE_OWN) {
            $salesQuery->bySalesperson($employeeId);
        } elseif ($saleIncludeType === CommissionTargetRule::INCLUDE_TYPE_ALL_EXCEPT_OWN) {
            $salesQuery->whereHas('salesperson', fn ($q) => $q->where('id', '!=', $employeeId));
        }
        $salesByPrefix = $salesQuery->selectRaw('prefix, SUM(total_usd) as total')->groupBy('prefix')->get()->keyBy('prefix');

        $returnsQuery = CustomerReturn::query()->approved()->received()->whereBetween('date', [$firstDay, $lastDay]);
        if ($saleIncludeType === CommissionTargetRule::INCLUDE_TYPE_OWN) {
            $returnsQuery->whereHas('salesperson', fn ($q) => $q->where('id', $employeeId));
        } elseif ($saleIncludeType === CommissionTargetRule::INCLUDE_TYPE_ALL_EXCEPT_OWN) {
            $returnsQuery->whereHas('salesperson', fn ($q) => $q->where('id', '!=', $employeeId));
        }
        $returnsByPrefix = $returnsQuery->selectRaw('prefix, SUM(total_usd) as total')->groupBy('prefix')->get()->keyBy('prefix');

        $paymentsQuery = CustomerPayment::query()->approved()->whereBetween('date', [$firstDay, $lastDay]);
        if ($paymentIncludeType === CommissionTargetRule::INCLUDE_TYPE_OWN) {
            $paymentsQuery->whereHas('customer', fn ($q) => $q->where('salesperson_id', $employeeId));
        } elseif ($paymentIncludeType === CommissionTargetRule::INCLUDE_TYPE_ALL_EXCEPT_OWN) {
            $paymentsQuery->whereHas('customer', fn ($q) => $q->where('salesperson_id', '!=', $employeeId));
        }
        $paymentsByPrefix = $paymentsQuery->selectRaw('prefix, SUM(amount_usd) as total')->groupBy('prefix')->get()->keyBy('prefix');

        $totalSales = 0.0;
        foreach ($salesByPrefix as $prefix => $row) {
            $t = (float) $row->total;
            $totalSales += $prefix === Sale::TAXPREFIX ? $t / $this->commissionTaxRate() : $t;
        }

        $totalReturns = 0.0;
        foreach ($returnsByPrefix as $prefix => $row) {
            $t = (float) $row->total;
            $totalReturns += $prefix === CustomerReturn::TAXPREFIX ? $t / $this->commissionTaxRate() : $t;
        }

        $totalPayments = 0.0;
        foreach ($paymentsByPrefix as $prefix => $row) {
            $t = (float) $row->total;
            $totalPayments += $prefix === CustomerPayment::TAXPREFIX ? $t / $this->commissionTaxRate() : $t;
        }

        return [$totalSales, $totalReturns, $totalPayments];
    }

    protected function calculateFuelCommission(CommissionTargetRule $rule, float $totalSales, float $totalPayments, float $totalReturns): float
    {
        $fuelAmount = ($totalPayments - $totalReturns + $totalSales) / 2;

        if ($rule->percent_type === CommissionTargetRule::PERCENTAGE_TYPE_FIXED) {
            return min($fuelAmount, $rule->maximum_amount) * ($rule->percent / 100);
        }

        if ($fuelAmount < $rule->maximum_amount) {
            $dynamicPercent = ($fuelAmount / $rule->maximum_amount) * $rule->percent;
            return $fuelAmount * ($dynamicPercent / 100);
        }

        return $rule->maximum_amount * ($rule->percent / 100);
    }

    protected function calculateSaleCommission(CommissionTargetRule $rule, float $totalSales): float
    {
        if ($totalSales < $rule->minimum_amount) {
            return 0.0;
        }

        if ($rule->percent_type === CommissionTargetRule::PERCENTAGE_TYPE_FIXED) {
            return $rule->minimum_amount * ($rule->percent / 100);
        }

        return min($totalSales, $rule->maximum_amount) * ($rule->percent / 100);
    }

    protected function calculatePaymentCommission(CommissionTargetRule $rule, float $totalPayments): float
    {
        if ($rule->percent_type === CommissionTargetRule::PERCENTAGE_TYPE_FIXED) {
            if ($totalPayments < $rule->minimum_amount) {
                return 0.0;
            }
            return min($totalPayments, $rule->maximum_amount) * ($rule->percent / 100);
        }

        if ($totalPayments <= $rule->maximum_amount) {
            $dynamicPercent = ($totalPayments / $rule->maximum_amount) * $rule->percent;
            return ($dynamicPercent / 100) * $totalPayments;
        }

        return $totalPayments * ($rule->percent / 100);
    }
}
