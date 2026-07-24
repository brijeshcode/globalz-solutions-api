<?php

use App\Models\Employees\CommissionTargetRule;
use App\Services\Employees\Commission\RewardCalculator;

function rewardRule(array $attrs): CommissionTargetRule
{
    return new CommissionTargetRule($attrs);
}

it('fixed calc + fixed reward pays full reward once minimum reached, else zero', function () {
    $calc = new RewardCalculator();
    $rule = rewardRule(['reward_calculation_type' => 'fixed', 'reward_type' => 'fixed', 'fixed_reward' => 1000]);

    expect($calc->calculate($rule, achievement: 10000, min: 10000, max: 15000)['amount'])->toBe(1000.0);
    expect($calc->calculate($rule, achievement: 12000, min: 10000, max: 15000)['amount'])->toBe(1000.0);
    expect($calc->calculate($rule, achievement: 9999, min: 10000, max: 15000)['amount'])->toBe(0.0);
});

it('dynamic calc + fixed reward scales from 0 toward max and caps at the fixed reward', function () {
    $calc = new RewardCalculator();
    $rule = rewardRule(['reward_calculation_type' => 'dynamic', 'reward_type' => 'fixed', 'fixed_reward' => 1000]);

    expect(round($calc->calculate($rule, 3000, 10000, 15000)['amount'], 2))->toBe(200.0);   // 3000/15000*1000
    expect(round($calc->calculate($rule, 10000, 10000, 15000)['amount'], 2))->toBe(666.67);
    expect($calc->calculate($rule, 15000, 10000, 15000)['amount'])->toBe(1000.0);
    expect($calc->calculate($rule, 99999, 10000, 15000)['amount'])->toBe(1000.0);            // capped
});

it('fixed calc + percent pays percent of capped achievement once minimum reached', function () {
    $calc = new RewardCalculator();
    $rule = rewardRule(['reward_calculation_type' => 'fixed', 'reward_type' => 'percent', 'percent' => 3]);

    expect($calc->calculate($rule, 12000, 10000, 12000)['amount'])->toBe(360.0);   // 3% * 12000
    expect($calc->calculate($rule, 9999, 10000, 12000)['amount'])->toBe(0.0);       // below min
    expect($calc->calculate($rule, 20000, 0, 0)['amount'])->toBe(600.0);            // no cap => full
});

it('dynamic calc + percent pays percent of capped achievement, no minimum gate', function () {
    $calc = new RewardCalculator();
    $rule = rewardRule(['reward_calculation_type' => 'dynamic', 'reward_type' => 'percent', 'percent' => 3]);

    expect($calc->calculate($rule, 3000, 0, 15000)['amount'])->toBe(90.0);
    expect($calc->calculate($rule, 15000, 0, 15000)['amount'])->toBe(450.0);
    expect($calc->calculate($rule, 20000, 0, 15000)['amount'])->toBe(450.0);   // capped at max
});
