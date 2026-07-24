<?php

namespace App\Services\Employees\Commission;

use App\Models\Employees\Employee;
use App\Models\Employees\EmployeeCommissionTarget;

/**
 * Entry point for commission calculation. Loads the employee's assigned commission target
 * for the month/year, runs every rule through RuleCalculator, sums the rewards, and shapes
 * the response. Day-by-day breakdown is returned only when $detailed is true.
 */
class CommissionCalculator
{
    public function __construct(
        private RuleCalculator $ruleCalculator,
        private DailyBusinessReport $dailyBusinessReport,
    ) {}

    public function forEmployee(int $employeeId, int $month, int $year, bool $detailed = false): array
    {
        $employee = Employee::find($employeeId);

        $assignment = EmployeeCommissionTarget::with('commissionTarget.rules')
            ->byEmployee($employeeId)
            ->byMonth($month)
            ->byYear($year)
            ->first();

        $commissions = [];
        $total = 0.0;

        $target = $assignment?->commissionTarget;

        if ($target) {
            foreach ($target->rules as $rule) {
                $row = $this->ruleCalculator->calculate($rule, $employeeId, $month, $year);

                $total += $row['reward']['amount'];

                unset($row['breakdown']);
                $commissions[] = $row;
            }
        }

        $response = [
            'business_summary' => [
                'employee_id'   => $employee?->id,
                'employee_name' => $employee?->name ?? 'N/A',
                'base_salary'   => $employee?->base_salary,
                'month'         => $month,
                'year'          => $year,
            ],
            'commission_target' => $target ? [
                'id'   => $target->id,
                'code' => $target->prefix . $target->code,
                'name' => $target->name,
            ] : null,
            'commissions'      => $commissions,
            'total_commission' => round($total, 2),
        ];

        // Day-by-day business grid (employee's OWN transactions) + gross/net totals footer.
        if ($detailed) {
            $report = $this->dailyBusinessReport->forEmployee($employeeId, $month, $year);
            $response['daily_business'] = $report['days'];
            $response['daily_totals'] = $report['totals'];
        }

        return $response;
    }
}
