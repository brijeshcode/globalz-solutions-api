<?php

namespace App\Services\Employees\Commission;

use App\Models\Employees\CommissionTargetRule;
use InvalidArgumentException;

/**
 * Pure reward math — the four combinations from docs/commission-calculation.md (Step 5 × Step 6).
 * No database access. Given an achievement and the target's min/max, returns the money + a
 * human-readable formula string.
 *
 * Gating rule: "fixed" calc types pay only once achievement reaches the minimum; "dynamic"
 * calc types scale from 0 toward the maximum (no minimum gate).
 * Edge rules: min = 0 means no threshold; max = 0 means no upper cap (use full achievement).
 */
class RewardCalculator
{
    /** @return array{amount: float, formula: string} */
    public function calculate(CommissionTargetRule $rule, float $achievement, float $min, float $max): array
    {
        $key = "{$rule->reward_calculation_type}:{$rule->reward_type}";

        return match ($key) {
            'fixed:fixed'     => $this->fixedFixed($rule, $achievement, $min),
            'dynamic:fixed'   => $this->dynamicFixed($rule, $achievement, $max),
            'fixed:percent'   => $this->fixedPercent($rule, $achievement, $min, $max),
            'dynamic:percent' => $this->dynamicPercent($rule, $achievement, $max),
            default           => throw new InvalidArgumentException("No reward rule for [{$key}]."),
        };
    }

    /** Full fixed reward once the minimum is reached, else 0. */
    private function fixedFixed(CommissionTargetRule $rule, float $achievement, float $min): array
    {
        $fixed = (float) $rule->fixed_reward;

        if ($achievement < $min) {
            return ['amount' => 0.0, 'formula' => "Achievement {$achievement} below minimum {$min}; no reward."];
        }

        return ['amount' => $fixed, 'formula' => "Reached minimum {$min}; full fixed reward {$fixed}."];
    }

    /** Fixed reward earned proportionally toward the maximum, capped at the fixed reward. Scales from 0. */
    private function dynamicFixed(CommissionTargetRule $rule, float $achievement, float $max): array
    {
        $fixed = (float) $rule->fixed_reward;

        if ($max <= 0) {
            $amount = $achievement > 0 ? $fixed : 0.0;
            return ['amount' => $amount, 'formula' => "No maximum set; paid full fixed reward {$fixed}."];
        }

        $amount = min(min($achievement, $max) / $max * $fixed, $fixed);

        return ['amount' => $amount, 'formula' => "min({$achievement}, {$max}) / {$max} × {$fixed} = {$amount}."];
    }

    /** Percent of the achievement (capped at max) once the minimum is reached, else 0. */
    private function fixedPercent(CommissionTargetRule $rule, float $achievement, float $min, float $max): array
    {
        if ($achievement < $min) {
            return ['amount' => 0.0, 'formula' => "Achievement {$achievement} below minimum {$min}; no reward."];
        }

        $percent = (float) $rule->percent;
        $base = $max > 0 ? min($achievement, $max) : $achievement;
        $amount = $base * ($percent / 100);

        return ['amount' => $amount, 'formula' => "{$percent}% × {$base} = {$amount}."];
    }

    /** Percent of the current achievement (capped at max) as it grows. No minimum gate. */
    private function dynamicPercent(CommissionTargetRule $rule, float $achievement, float $max): array
    {
        $percent = (float) $rule->percent;
        $base = $max > 0 ? min($achievement, $max) : $achievement;
        $amount = $base * ($percent / 100);

        return ['amount' => $amount, 'formula' => "{$percent}% × {$base} = {$amount}."];
    }
}
