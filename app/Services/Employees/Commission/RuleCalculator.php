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
        $target = $this->target($rule, $employeeId, $month, $year);
        [$min, $max] = [$target['min'], $target['max']];
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
                'amount'        => round($reward['amount'], 2),
                'reward_type'   => $rule->reward_type,
                'calc_type'     => $rule->reward_calculation_type,
                'percent'         => (float) $rule->percent,                                  // configured % (used when reward_type = percent)
                'effective_percent' => $reward['effective_percent'],                          // actual % paid on achievement (percent × progress)
                'fixed_reward'  => $rule->fixed_reward !== null ? (float) $rule->fixed_reward : null, // configured amount (used when reward_type = fixed)
                'target_reward' => $this->targetReward($rule, $max),                          // what the employee gets on FULL target completion
                'formula'       => $reward['formula'],
            ],
            // Plain-language, step-by-step explanation for the salesman. Each sentence is produced
            // by the same phase that computes its number, so it stays in sync with the math.
            'note'         => [
                ['step' => 'Month',        'text' => $this->periodNote($rule, $month, $year)],
                ['step' => 'What counted', 'text' => $achievement['note']],
                ['step' => 'Your goal',    'text' => $target['note']],
                ['step' => 'You earned',   'text' => $reward['note']],
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

    /** Plain-language "Month" step: the time span this rule looked at. */
    private function periodNote(CommissionTargetRule $rule, int $month, int $year): string
    {
        if ($rule->period === CommissionTargetRule::PERIOD_YEARLY) {
            return "For the year {$year}.";
        }

        return 'For ' . Carbon::create($year, $month, 1)->format('F Y') . '.';
    }

    /**
     * Phase 2+3 — net achievement for the collection type (sale/payment/fuel), all VAT-excluded.
     *   sale    = sales − returns
     *   payment = payments − returns
     *   fuel    = (payments − returns + sales) / 2
     *
     * @return array{amount: float, note: string, breakdown: array<string, mixed>}
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
        $net = $sales['amount'] - $returns['amount'];

        $note = $returns['amount'] > 0
            ? "You made {$this->money($sales['amount'])} in sales. We removed {$this->money($returns['amount'])} for returns. That leaves {$this->money($net)}."
            : "You made {$this->money($net)} in sales.";

        return [
            'amount'    => $net,
            'note'      => $note,
            'breakdown' => ['sales' => $sales['daily'], 'returns' => $returns['daily']],
        ];
    }

    private function paymentAchievement(string $include, int $employeeId, Carbon $start, Carbon $end): array
    {
        $payments = $this->aggregator->payments($employeeId, $include, $start, $end);
        $returns  = $this->aggregator->returns($employeeId, $include, $start, $end);
        $net = $payments['amount'] - $returns['amount'];

        $note = $returns['amount'] > 0
            ? "You collected {$this->money($payments['amount'])} in payments. We removed {$this->money($returns['amount'])} for returns. That leaves {$this->money($net)}."
            : "You collected {$this->money($net)} in payments.";

        return [
            'amount'    => $net,
            'note'      => $note,
            'breakdown' => ['payments' => $payments['daily'], 'returns' => $returns['daily']],
        ];
    }

    private function fuelAchievement(string $include, int $employeeId, Carbon $start, Carbon $end): array
    {
        $payments = $this->aggregator->payments($employeeId, $include, $start, $end);
        $returns  = $this->aggregator->returns($employeeId, $include, $start, $end);
        $sales    = $this->aggregator->sales($employeeId, $include, $start, $end);
        $net = ($payments['amount'] - $returns['amount'] + $sales['amount']) / 2;

        $note = $returns['amount'] > 0
            ? "We combined your payments {$this->money($payments['amount'])} and sales {$this->money($sales['amount'])}, removed {$this->money($returns['amount'])} for returns, then took half. That leaves {$this->money($net)}."
            : "We combined your payments {$this->money($payments['amount'])} and sales {$this->money($sales['amount'])}, then took half. That leaves {$this->money($net)}.";

        return [
            'amount'    => $net,
            'note'      => $note,
            'breakdown' => ['payments' => $payments['daily'], 'returns' => $returns['daily'], 'sales' => $sales['daily']],
        ];
    }

    /**
     * Phase 4 — the target min/max.
     *   set  = the rule's minimum_amount / maximum_amount
     *   auto = average achievement over the previous N months × (1 + push%) = T, applied per
     *          the rule's auto_target_type:
     *            min  -> [T, 0]   (threshold: must reach T)  — default
     *            max  -> [0, T]   (ceiling: scale from 0)
     *            both -> [T, T]   (T is both threshold and cap)
     *
     * @return array{min: float, max: float, note: string}
     */
    private function target(CommissionTargetRule $rule, int $employeeId, int $month, int $year): array
    {
        if ($rule->amount_type === CommissionTargetRule::AMOUNT_TYPE_SET) {
            [$min, $max] = [(float) $rule->minimum_amount, (float) $rule->maximum_amount];
        } else {
            $t = $this->autoTarget($rule, $employeeId, $month, $year);

            [$min, $max] = match ($rule->auto_target_type) {
                CommissionTargetRule::AUTO_TARGET_MAX  => [0.0, $t],
                CommissionTargetRule::AUTO_TARGET_BOTH => [$t, $t],
                default                                => [$t, 0.0], // AUTO_TARGET_MIN (default)
            };
        }

        return ['min' => $min, 'max' => $max, 'note' => $this->targetNote($min, $max)];
    }

    /** Plain-language "Your goal" step from the resolved min/max (set or auto alike). */
    private function targetNote(float $min, float $max): string
    {
        if ($max > 0) {
            return "Your goal was {$this->money($max)}.";
        }

        if ($min > 0) {
            return "You needed to reach at least {$this->money($min)}.";
        }

        return 'There was no fixed goal — you earn on everything you collect.';
    }

    /**
     * The reward the employee earns on FULL target completion (the "goal" figure to show them):
     *   - fixed reward  -> the configured fixed_reward
     *   - percent, max > 0 -> percent × max (the capped ceiling)
     *   - percent, max = 0 -> null (uncapped: earning grows with achievement, no fixed goal)
     */
    private function targetReward(CommissionTargetRule $rule, float $max): ?float
    {
        if ($rule->reward_type === CommissionTargetRule::REWARD_TYPE_FIXED) {
            return $rule->fixed_reward !== null ? (float) $rule->fixed_reward : null;
        }

        return $max > 0 ? round((float) $rule->percent / 100 * $max, 2) : null;
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

    /** Money for the plain note: thousands separators, 2 decimals (e.g. 1,000.00). */
    private function money(float $amount): string
    {
        return number_format($amount, 2);
    }
}
