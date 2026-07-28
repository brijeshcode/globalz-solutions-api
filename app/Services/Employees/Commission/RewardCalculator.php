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
 *
 * Capping: max is the target. When the rule's is_capped is true, the reward stops growing
 * once achievement reaches max (base is clamped to max, progress clamped to 100%). When
 * is_capped is false, max is only the progress target and the reward keeps growing past it.
 * is_capped has no effect on fixed:fixed (it never uses max).
 */
class RewardCalculator
{
    /** @return array{amount: float, formula: string, effective_percent: float} */
    public function calculate(CommissionTargetRule $rule, float $achievement, float $min, float $max): array
    {
        $key = "{$rule->reward_calculation_type}:{$rule->reward_type}";

        $result = match ($key) {
            'fixed:fixed'     => $this->fixedFixed($rule, $achievement, $min),
            'dynamic:fixed'   => $this->dynamicFixed($rule, $achievement, $max),
            'fixed:percent'   => $this->fixedPercent($rule, $achievement, $min, $max),
            'dynamic:percent' => $this->dynamicPercent($rule, $achievement, $max),
            default           => throw new InvalidArgumentException("No reward rule for [{$key}]."),
        };

        // The actual rate paid as a fraction of achievement (e.g. 10% × 20% progress => 2%).
        $result['effective_percent'] = $achievement > 0 ? round($result['amount'] / $achievement * 100, 2) : 0.0;

        return $result;
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

    /** Fixed reward earned proportionally toward the maximum. Scales from 0; capped at the fixed reward when is_capped. */
    private function dynamicFixed(CommissionTargetRule $rule, float $achievement, float $max): array
    {
        $fixed = (float) $rule->fixed_reward;

        if ($max <= 0) {
            $amount = $achievement > 0 ? $fixed : 0.0;
            return ['amount' => $amount, 'formula' => "No maximum set; paid full fixed reward {$fixed}."];
        }

        $ratio = $rule->is_capped ? min($achievement, $max) / $max : $achievement / $max;
        $amount = $ratio * $fixed;

        return ['amount' => $amount, 'formula' => "{$ratio} × {$fixed} = {$amount}."];
    }

    /** Percent of the achievement (capped at max when is_capped) once the minimum is reached, else 0. */
    private function fixedPercent(CommissionTargetRule $rule, float $achievement, float $min, float $max): array
    {
        if ($achievement < $min) {
            return ['amount' => 0.0, 'formula' => "Achievement {$achievement} below minimum {$min}; no reward."];
        }

        $percent = (float) $rule->percent;
        $base = ($max > 0 && $rule->is_capped) ? min($achievement, $max) : $achievement;
        $amount = $base * ($percent / 100);

        return ['amount' => $amount, 'formula' => "{$percent}% × {$base} = {$amount}."];
    }

    /**
     * Percent of achievement scaled by progress toward max. No minimum gate.
     * is_capped clamps base and progress at max/100%; otherwise both grow past the target.
     * max = 0 means no target, so progress = 1 (flat percent).
     */
    private function dynamicPercent(CommissionTargetRule $rule, float $achievement, float $max): array
    {
        $percent = (float) $rule->percent;

        if ($max <= 0) {
            $amount = $achievement * ($percent / 100);
            return ['amount' => $amount, 'formula' => "No target set; {$percent}% × {$achievement} = {$amount}."];
        }

        $base     = $rule->is_capped ? min($achievement, $max) : $achievement;
        $progress = $base / $max;
        $amount   = $base * ($percent / 100) * $progress;

        $progressPct = round($progress * 100, 4);

        return ['amount' => $amount, 'formula' => "{$percent}% × {$progressPct}% progress × {$base} = {$amount}."];
    }
}
