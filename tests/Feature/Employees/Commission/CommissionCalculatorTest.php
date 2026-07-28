<?php

use App\Models\Customers\CustomerPayment;
use App\Models\Customers\Sale;
use App\Models\Employees\CommissionTarget;
use App\Models\Employees\CommissionTargetRule;
use App\Models\Employees\Employee;
use App\Models\Employees\EmployeeCommissionTarget;
use App\Models\Setups\TaxCode;
use App\Models\User;
use App\Services\Employees\Commission\CommissionCalculator;

function assignTarget(Employee $employee, CommissionTarget $target, int $month = 6, int $year = 2026): void
{
    EmployeeCommissionTarget::create([
        'employee_id'          => $employee->id,
        'commission_target_id' => $target->id,
        'month'                => $month,
        'year'                 => $year,
    ]);
}

it('sums rewards across all matching rules for the employee', function () {
    $employee = Employee::factory()->create();
    $target = CommissionTarget::factory()->create();

    CommissionTargetRule::create([
        'commission_target_id' => $target->id, 'comission_label' => 'Rule',
        'type' => 'sale', 'period' => 'monthly', 'include_type' => 'Own',
        'amount_type' => 'set', 'minimum_amount' => 1000, 'maximum_amount' => 0,
        'reward_calculation_type' => 'fixed', 'reward_type' => 'percent', 'percent' => 3,
    ]);
    CommissionTargetRule::create([
        'commission_target_id' => $target->id, 'comission_label' => 'Rule',
        'type' => 'sale', 'period' => 'monthly', 'include_type' => 'Own',
        'amount_type' => 'set', 'minimum_amount' => 2000, 'maximum_amount' => 0,
        'reward_calculation_type' => 'fixed', 'reward_type' => 'percent', 'percent' => 2,
    ]);
    assignTarget($employee, $target);

    // Own TAXFREE sale of 4000 (net 4000, no VAT).
    Sale::factory()->create([
        'salesperson_id' => $employee->id, 'prefix' => Sale::TAXFREEPREFIX,
        'total_usd' => 4000, 'date' => '2026-06-15', 'approved_by' => User::factory(),
    ]);

    $result = app(CommissionCalculator::class)->forEmployee($employee->id, 6, 2026);

    // Rule 1: 3% * 4000 = 120 ; Rule 2: 2% * 4000 = 80 ; total 200
    expect(round($result['total_commission'], 2))->toBe(200.0);
    expect($result['commissions'])->toHaveCount(2);
    // Configured reward settings are returned so the frontend can show the % / amount.
    expect($result['commissions'][0]['reward'])->toMatchArray([
        'reward_type' => 'percent', 'percent' => 3.0, 'fixed_reward' => null,
    ]);
    expect($result)->not->toHaveKey('daily_business');

    $detailed = app(CommissionCalculator::class)->forEmployee($employee->id, 6, 2026, detailed: true);
    expect($detailed)->toHaveKey('daily_business');
});

it('computes a sale rule as net sales (sales - returns)', function () {
    $employee = Employee::factory()->create();
    $target = CommissionTarget::factory()->create();

    CommissionTargetRule::create([
        'commission_target_id' => $target->id, 'comission_label' => 'Rule',
        'type' => 'sale', 'period' => 'monthly', 'include_type' => 'Own',
        'amount_type' => 'set', 'minimum_amount' => 0, 'maximum_amount' => 0,
        'reward_calculation_type' => 'dynamic', 'reward_type' => 'percent', 'percent' => 10,
    ]);
    assignTarget($employee, $target);

    // Sale 1000 (taxfree) ; Return 200 (taxfree) => net achievement 800.
    Sale::factory()->create([
        'salesperson_id' => $employee->id, 'prefix' => Sale::TAXFREEPREFIX,
        'total_usd' => 1000, 'date' => '2026-06-10', 'approved_by' => User::factory(),
    ]);
    \App\Models\Customers\CustomerReturn::factory()->approved()->received()->create([
        'salesperson_id' => $employee->id, 'prefix' => \App\Models\Customers\CustomerReturn::TAXFREEPREFIX,
        'total_usd' => 200, 'date' => '2026-06-12',
    ]);

    $result = app(CommissionCalculator::class)->forEmployee($employee->id, 6, 2026);

    // net 800 ; dynamic percent no cap => 10% * 800 = 80
    expect($result['commissions'][0]['achievement'])->toBe(800.0);
    expect(round($result['total_commission'], 2))->toBe(80.0);
});

it('computes a fuel rule as (payments - returns + sales) / 2, VAT excluded', function () {
    $employee = Employee::factory()->create();
    $target = CommissionTarget::factory()->create();

    CommissionTargetRule::create([
        'commission_target_id' => $target->id, 'comission_label' => 'Rule',
        'type' => 'fuel', 'period' => 'monthly', 'include_type' => 'Own',
        'amount_type' => 'set', 'minimum_amount' => 0, 'maximum_amount' => 0,
        'reward_calculation_type' => 'dynamic', 'reward_type' => 'percent', 'percent' => 10,
    ]);
    assignTarget($employee, $target);

    // Sale 600 (taxfree) ; Payment 1000 (taxfree) ; no returns => (1000 - 0 + 600)/2 = 800
    Sale::factory()->create([
        'salesperson_id' => $employee->id, 'prefix' => Sale::TAXFREEPREFIX,
        'total_usd' => 600, 'date' => '2026-06-10', 'approved_by' => User::factory(),
    ]);
    CustomerPayment::factory()->create([
        'prefix' => CustomerPayment::TAXFREEPREFIX, 'amount_usd' => 1000,
        'date' => '2026-06-12', 'approved_by' => User::factory(),
        'account_id' => \App\Models\Accounts\Account::factory(),
        'customer_id' => \App\Models\Customers\Customer::factory()->create(['salesperson_id' => $employee->id]),
    ]);

    $result = app(CommissionCalculator::class)->forEmployee($employee->id, 6, 2026);

    // achievement 800 ; dynamic percent, no cap => 10% * 800 = 80
    expect($result['commissions'][0]['achievement'])->toBe(800.0);
    expect(round($result['total_commission'], 2))->toBe(80.0);
});

it('treats the auto target as the ceiling (max) with no floor (min = 0)', function () {
    $employee = Employee::factory()->create();
    $target = CommissionTarget::factory()->create();

    // Auto: average of the previous 2 months, +0% push, applied as the CEILING (max).
    CommissionTargetRule::create([
        'commission_target_id' => $target->id, 'comission_label' => 'Rule',
        'type' => 'sale', 'period' => 'monthly', 'include_type' => 'Own',
        'amount_type' => 'auto', 'auto_target_type' => 'max', 'number_of_months' => 2, 'push_target_percent' => 0,
        'minimum_amount' => 0, 'maximum_amount' => 0,
        'reward_calculation_type' => 'dynamic', 'reward_type' => 'percent', 'percent' => 10,
    ]);
    assignTarget($employee, $target, month: 3, year: 2026);

    // Previous 2 months: Jan 1000 + Feb 2000 => avg 1500 => auto target (max) = 1500.
    Sale::factory()->create([
        'salesperson_id' => $employee->id, 'prefix' => Sale::TAXFREEPREFIX,
        'total_usd' => 1000, 'date' => '2026-01-15', 'approved_by' => User::factory(),
    ]);
    Sale::factory()->create([
        'salesperson_id' => $employee->id, 'prefix' => Sale::TAXFREEPREFIX,
        'total_usd' => 2000, 'date' => '2026-02-15', 'approved_by' => User::factory(),
    ]);
    // Current month (March) achievement 3000 — above the ceiling.
    Sale::factory()->create([
        'salesperson_id' => $employee->id, 'prefix' => Sale::TAXFREEPREFIX,
        'total_usd' => 3000, 'date' => '2026-03-15', 'approved_by' => User::factory(),
    ]);

    $result = app(CommissionCalculator::class)->forEmployee($employee->id, 3, 2026);

    // Dynamic percent, min 0 / max 1500 => base = min(3000, 1500) = 1500 => progress = 1500/1500 = 100% => 10% × 100% × 1500 = 150.
    // (If auto were treated as a floor, this would wrongly be 10% × 100% × 3000 = 300.)
    expect($result['commissions'][0]['target'])->toMatchArray(['min' => 0.0, 'max' => 1500.0]);
    expect(round($result['total_commission'], 2))->toBe(150.0);
});

it('auto with target_type=min uses the computed value as a threshold (fixed reward withheld below it)', function () {
    $employee = Employee::factory()->create();
    $target = CommissionTarget::factory()->create();

    CommissionTargetRule::create([
        'commission_target_id' => $target->id, 'comission_label' => 'Rule',
        'type' => 'sale', 'period' => 'monthly', 'include_type' => 'Own',
        'amount_type' => 'auto', 'auto_target_type' => 'min',
        'number_of_months' => 2, 'push_target_percent' => 0,
        'minimum_amount' => 0, 'maximum_amount' => 0,
        'reward_calculation_type' => 'fixed', 'reward_type' => 'fixed', 'fixed_reward' => 500, 'percent' => 0,
    ]);
    assignTarget($employee, $target, month: 3, year: 2026);

    // Prev 2 months: Jan 1000 + Feb 2000 => avg 1500 => threshold (min) = 1500.
    Sale::factory()->create([
        'salesperson_id' => $employee->id, 'prefix' => Sale::TAXFREEPREFIX,
        'total_usd' => 1000, 'date' => '2026-01-15', 'approved_by' => User::factory(),
    ]);
    Sale::factory()->create([
        'salesperson_id' => $employee->id, 'prefix' => Sale::TAXFREEPREFIX,
        'total_usd' => 2000, 'date' => '2026-02-15', 'approved_by' => User::factory(),
    ]);
    // Current March achievement 1000 — BELOW the 1500 threshold.
    Sale::factory()->create([
        'salesperson_id' => $employee->id, 'prefix' => Sale::TAXFREEPREFIX,
        'total_usd' => 1000, 'date' => '2026-03-15', 'approved_by' => User::factory(),
    ]);

    $result = app(CommissionCalculator::class)->forEmployee($employee->id, 3, 2026);

    // Auto target applied as the minimum; achievement 1000 < 1500 => no reward.
    // (Previously auto forced min = 0, so a fixed reward was paid for nothing.)
    expect($result['commissions'][0]['target'])->toMatchArray(['min' => 1500.0, 'max' => 0.0]);
    expect(round($result['total_commission'], 2))->toBe(0.0);
});

it('returns the daily business grid and gross/net totals when detailed', function () {
    TaxCode::factory()->default()->withTaxPercent(11)->create();
    $employee = Employee::factory()->create();

    // Own approved sales: INV 1110 on Jul 7, INX 500 on Jul 8.
    Sale::factory()->create([
        'salesperson_id' => $employee->id, 'prefix' => Sale::TAXPREFIX,
        'total_usd' => 1110, 'date' => '2026-07-07', 'approved_by' => User::factory(),
    ]);
    Sale::factory()->create([
        'salesperson_id' => $employee->id, 'prefix' => Sale::TAXFREEPREFIX,
        'total_usd' => 500, 'date' => '2026-07-08', 'approved_by' => User::factory(),
    ]);

    $result = app(CommissionCalculator::class)->forEmployee($employee->id, 7, 2026, detailed: true);

    // Every day of July present, gross values in the cells.
    expect($result['daily_business'])->toHaveCount(31);
    $day7 = collect($result['daily_business'])->firstWhere('day', 7);
    expect($day7['sales']['INV'])->toMatchArray(['count' => 1, 'total' => 1110.0]);
    expect($day7['sales']['INX'])->toMatchArray(['count' => 0, 'total' => 0]);

    // Footer: gross INV 1110 ; without VAT INV 1110/1.11 = 1000 ; INX 500 unchanged ; month net 1500.
    $totals = $result['daily_totals'];
    expect($totals['gross']['sales']['INV']['total'])->toBe(1110.0);
    expect(round($totals['without_vat']['sales']['INV']['total'], 2))->toBe(1000.0);
    expect($totals['without_vat']['sales']['INX']['total'])->toBe(500.0);
    expect($totals['month_total']['sales'])->toBe(1500.0);
});

it('reports target_reward — the goal amount on full target completion', function () {
    $employee = Employee::factory()->create();
    $target = CommissionTarget::factory()->create();

    // Capped percent rule: 3% up to max 5000 => goal 150.
    CommissionTargetRule::create([
        'commission_target_id' => $target->id, 'comission_label' => 'Sale',
        'type' => 'sale', 'period' => 'monthly', 'include_type' => 'Own',
        'amount_type' => 'set', 'minimum_amount' => 1000, 'maximum_amount' => 5000,
        'reward_calculation_type' => 'dynamic', 'reward_type' => 'percent', 'percent' => 3,
    ]);
    // Fixed reward rule: goal = the fixed amount 500.
    CommissionTargetRule::create([
        'commission_target_id' => $target->id, 'comission_label' => 'Payment',
        'type' => 'payment', 'period' => 'monthly', 'include_type' => 'Own',
        'amount_type' => 'set', 'minimum_amount' => 0, 'maximum_amount' => 0,
        'reward_calculation_type' => 'fixed', 'reward_type' => 'fixed', 'fixed_reward' => 500, 'percent' => 0,
    ]);
    assignTarget($employee, $target);

    $result = app(CommissionCalculator::class)->forEmployee($employee->id, 6, 2026);

    $sale = collect($result['commissions'])->firstWhere('type', 'sale');
    $payment = collect($result['commissions'])->firstWhere('type', 'payment');

    expect($sale['reward']['target_reward'])->toBe(150.0);      // 3% × 5000 (capped)
    expect($payment['reward']['target_reward'])->toBe(500.0);   // the fixed reward
});

it('dynamic percent (capped) pays percent scaled by progress toward the target', function () {
    $employee = Employee::factory()->create();
    $target = CommissionTarget::factory()->create();

    // Target 1000, 10%. Achievement 200 => 20% progress => 10% × 20% × 200 = 4.
    CommissionTargetRule::create([
        'commission_target_id' => $target->id, 'comission_label' => 'Rule',
        'type' => 'sale', 'period' => 'monthly', 'include_type' => 'Own',
        'amount_type' => 'set', 'minimum_amount' => 0, 'maximum_amount' => 1000, 'is_capped' => true,
        'reward_calculation_type' => 'dynamic', 'reward_type' => 'percent', 'percent' => 10,
    ]);
    assignTarget($employee, $target);

    Sale::factory()->create([
        'salesperson_id' => $employee->id, 'prefix' => Sale::TAXFREEPREFIX,
        'total_usd' => 200, 'date' => '2026-06-10', 'approved_by' => User::factory(),
    ]);

    $result = app(CommissionCalculator::class)->forEmployee($employee->id, 6, 2026);

    expect($result['commissions'][0]['achievement'])->toBe(200.0);
    expect(round($result['total_commission'], 2))->toBe(4.0);
    // Effective rate paid on achievement: 10% × 20% progress = 2%.
    expect($result['commissions'][0]['reward']['effective_percent'])->toBe(2.0);
});

it('dynamic percent (uncapped) keeps growing past the target', function () {
    $employee = Employee::factory()->create();
    $target = CommissionTarget::factory()->create();

    // Target 1000, 10%, NOT capped. Achievement 1500 => 150% progress => 10% × 150% × 1500 = 225.
    CommissionTargetRule::create([
        'commission_target_id' => $target->id, 'comission_label' => 'Rule',
        'type' => 'sale', 'period' => 'monthly', 'include_type' => 'Own',
        'amount_type' => 'set', 'minimum_amount' => 0, 'maximum_amount' => 1000, 'is_capped' => false,
        'reward_calculation_type' => 'dynamic', 'reward_type' => 'percent', 'percent' => 10,
    ]);
    assignTarget($employee, $target);

    Sale::factory()->create([
        'salesperson_id' => $employee->id, 'prefix' => Sale::TAXFREEPREFIX,
        'total_usd' => 1500, 'date' => '2026-06-10', 'approved_by' => User::factory(),
    ]);

    $result = app(CommissionCalculator::class)->forEmployee($employee->id, 6, 2026);

    expect($result['commissions'][0]['achievement'])->toBe(1500.0);
    expect(round($result['total_commission'], 2))->toBe(225.0);
});

it('fixed percent (uncapped) pays percent on the full achievement past the max', function () {
    $employee = Employee::factory()->create();
    $target = CommissionTarget::factory()->create();

    // 10% of full achievement, no cap. Achievement 3000, max 2000 => 10% × 3000 = 300.
    CommissionTargetRule::create([
        'commission_target_id' => $target->id, 'comission_label' => 'Rule',
        'type' => 'sale', 'period' => 'monthly', 'include_type' => 'Own',
        'amount_type' => 'set', 'minimum_amount' => 0, 'maximum_amount' => 2000, 'is_capped' => false,
        'reward_calculation_type' => 'fixed', 'reward_type' => 'percent', 'percent' => 10,
    ]);
    assignTarget($employee, $target);

    Sale::factory()->create([
        'salesperson_id' => $employee->id, 'prefix' => Sale::TAXFREEPREFIX,
        'total_usd' => 3000, 'date' => '2026-06-10', 'approved_by' => User::factory(),
    ]);

    $result = app(CommissionCalculator::class)->forEmployee($employee->id, 6, 2026);

    expect(round($result['total_commission'], 2))->toBe(300.0);
});

it('returns zero commission when the employee has no assigned target', function () {
    $employee = Employee::factory()->create();

    $result = app(CommissionCalculator::class)->forEmployee($employee->id, 6, 2026);

    expect($result['total_commission'])->toBe(0.0);
    expect($result['commissions'])->toBeEmpty();
    expect($result['commission_target'])->toBeNull();
});
