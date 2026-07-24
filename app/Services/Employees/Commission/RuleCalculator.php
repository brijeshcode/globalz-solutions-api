<?php

namespace App\Services\Employees\Commission;

use App\Models\Employees\CommissionTargetRule;
use Carbon\Carbon;

/**
 * Calculates the commission for a single rule, following docs/commission-calculation.md:
 *   1. period      -> the date range of transactions
 *   2. achievement -> net amount for the rule's collection type + include type
 *   3. target      -> the min/max the achievement is measured against (set or auto)
 *   4. reward      -> the money (delegated to RewardCalculator)
 *
 * Each phase is a clearly-named method. To support a new collection type, amount type,
 * or reward combination, add a match() arm in the relevant method.
 */
class RuleCalculator
{
    public function __construct(
        private TransactionAggregator $aggregator,
        private RewardCalculator $rewardCalculator,
    ) {}

    /** @return array<string, mixed> the per-rule result row */
    public function calculate(CommissionTargetRule $rule, int $employeeId, int $month, int $year): array
    {
        [$start, $end] = $this->periodRange($rule, $month, $year);
        $achievement = $this->achievement($rule, $employeeId, $start, $end);
        [$min, $max] = $this->target($rule, $employeeId, $month, $year);
        $reward = $this->rewardCalculator->calculate($rule, $achievement['amount'], $min, $max);

        return [
            'rule_id'      => $rule->id,
            'type'         => $rule->type,
            'period'       => $rule->period,
            'include_type' => $rule->include_type,
            'label'        => $rule->comission_label,
            'achievement'  => round($achievement['amount'], 2),
            'target'       => [
                'amount_type' => $rule->amount_type,
                'min'         => round($min, 2),
                'max'         => round($max, 2),
                'met'         => $achievement['amount'] >= $min,
            ],
            'reward'       => [
                'amount'      => round($reward['amount'], 2),
                'reward_type' => $rule->reward_type,
                'calc_type'   => $rule->reward_calculation_type,
                'formula'     => $reward['formula'],
            ],
            'breakdown'    => $achievement['breakdown'],
        ];
    }

    /** Phase 1 — monthly uses the requested month; yearly uses the whole year. */
    private function periodRange(CommissionTargetRule $rule, int $month, int $year): array
    {
        $anchor = Carbon::create($year, $month, 1);

        return $rule->period === CommissionTargetRule::PERIOD_YEARLY
            ? [$anchor->copy()->startOfYear(), $anchor->copy()->endOfYear()]
            : [$anchor->copy()->startOfMonth(), $anchor->copy()->endOfMonth()];
    }

    /**
     * Phase 2+3 — net achievement for the collection type (sale/payment/fuel), all VAT-excluded.
     *   sale    = sales − returns
     *   payment = payments only
     *   fuel    = (payments − returns + sales) / 2
     *
     * @return array{amount: float, breakdown: array<string, mixed>}
     */
    private function achievement(CommissionTargetRule $rule, int $employeeId, Carbon $start, Carbon $end): array
    {
        return match ($rule->type) {
            CommissionTargetRule::TYPE_PAYMENT => $this->paymentAchievement($rule->include_type, $employeeId, $start, $end),
            CommissionTargetRule::TYPE_FUEL    => $this->fuelAchievement($rule->include_type, $employeeId, $start, $end),
            default                            => $this->saleAchievement($rule->include_type, $employeeId, $start, $end),
        };
    }

    private function saleAchievement(string $include, int $employeeId, Carbon $start, Carbon $end): array
    {
        $sales = $this->aggregator->sales($employeeId, $include, $start, $end);
        $returns = $this->aggregator->returns($employeeId, $include, $start, $end);

        return [
            'amount'    => $sales['amount'] - $returns['amount'],
            'breakdown' => ['sales' => $sales['daily'], 'returns' => $returns['daily']],
        ];
    }

    private function paymentAchievement(string $include, int $employeeId, Carbon $start, Carbon $end): array
    {
        $payments = $this->aggregator->payments($employeeId, $include, $start, $end);

        return ['amount' => $payments['amount'], 'breakdown' => ['payments' => $payments['daily']]];
    }

    private function fuelAchievement(string $include, int $employeeId, Carbon $start, Carbon $end): array
    {
        $payments = $this->aggregator->payments($employeeId, $include, $start, $end);
        $returns  = $this->aggregator->returns($employeeId, $include, $start, $end);
        $sales    = $this->aggregator->sales($employeeId, $include, $start, $end);

        return [
            'amount'    => ($payments['amount'] - $returns['amount'] + $sales['amount']) / 2,
            'breakdown' => ['payments' => $payments['daily'], 'returns' => $returns['daily'], 'sales' => $sales['daily']],
        ];
    }

    /**
     * Phase 4 — the target min/max.
     *   set  = the rule's minimum_amount / maximum_amount
     *   auto = average achievement over the previous N months × (1 + push%) is the target
     *          ceiling (max); there is no floor on auto (min = 0)
     *
     * @return array{0: float, 1: float} [min, max]
     */
    private function target(CommissionTargetRule $rule, int $employeeId, int $month, int $year): array
    {
        if ($rule->amount_type === CommissionTargetRule::AMOUNT_TYPE_AUTO) {
            return [0.0, $this->autoTarget($rule, $employeeId, $month, $year)];
        }

        return [(float) $rule->minimum_amount, (float) $rule->maximum_amount];
    }

    /** Average of the previous N months' achievement, pushed up by push_target_percent. */
    private function autoTarget(CommissionTargetRule $rule, int $employeeId, int $month, int $year): float
    {
        $months = max((int) $rule->number_of_months, 0);
        if ($months === 0) {
            return 0.0;
        }

        $sum = 0.0;
        for ($i = 1; $i <= $months; $i++) {
            $anchor = Carbon::create($year, $month, 1)->subMonths($i);
            $sum += $this->achievement($rule, $employeeId, $anchor->copy()->startOfMonth(), $anchor->copy()->endOfMonth())['amount'];
        }

        $average = $sum / $months;

        return $average * (1 + ((float) $rule->push_target_percent / 100));
    }
}
