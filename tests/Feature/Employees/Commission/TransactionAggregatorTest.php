<?php

use App\Models\Customers\Sale;
use App\Models\Employees\CommissionTargetRule;
use App\Models\Employees\Employee;
use App\Models\Setups\TaxCode;
use App\Models\User;
use App\Services\Employees\Commission\TransactionAggregator;
use Carbon\Carbon;

it('strips VAT using the default tax code rate', function () {
    TaxCode::factory()->default()->withTaxPercent(11)->create();
    $employee = Employee::factory()->create();

    // TAX sale of 1110 => net 1110 / 1.11 = 1000 ; TAXFREE sale of 500 => net 500
    Sale::factory()->create([
        'salesperson_id' => $employee->id,
        'prefix'         => Sale::TAXPREFIX,
        'total_usd'      => 1110,
        'date'           => '2026-06-10',
        'approved_by'    => User::factory(),
    ]);
    Sale::factory()->create([
        'salesperson_id' => $employee->id,
        'prefix'         => Sale::TAXFREEPREFIX,
        'total_usd'      => 500,
        'date'           => '2026-06-11',
        'approved_by'    => User::factory(),
    ]);

    $start = Carbon::create(2026, 6, 1)->startOfDay();
    $end = Carbon::create(2026, 6, 30)->endOfDay();

    $sales = app(TransactionAggregator::class)
        ->sales($employee->id, CommissionTargetRule::INCLUDE_TYPE_OWN, $start, $end);

    expect(round($sales['amount'], 2))->toBe(1500.0);
    expect($sales['daily'])->toHaveCount(2);
});

it('uses the configured rate, not a hardcoded 11%', function () {
    TaxCode::factory()->default()->withTaxPercent(20)->create();
    $employee = Employee::factory()->create();

    // TAX sale of 1200 => net 1200 / 1.20 = 1000
    Sale::factory()->create([
        'salesperson_id' => $employee->id,
        'prefix'         => Sale::TAXPREFIX,
        'total_usd'      => 1200,
        'date'           => '2026-06-10',
        'approved_by'    => User::factory(),
    ]);

    $start = Carbon::create(2026, 6, 1)->startOfDay();
    $end = Carbon::create(2026, 6, 30)->endOfDay();

    $sales = app(TransactionAggregator::class)
        ->sales($employee->id, CommissionTargetRule::INCLUDE_TYPE_OWN, $start, $end);

    expect(round($sales['amount'], 2))->toBe(1000.0);
});
