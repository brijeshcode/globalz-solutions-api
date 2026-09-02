<?php

use App\Models\Accounts\Account;
use App\Models\Employees\Employee;
use App\Models\Employees\Salary;

/**
 * The account balance is adjusted by deltas (add/remove) rather than recalculated,
 * so every point in a salary's soft-delete lifecycle must keep it consistent.
 */

function makeSalary(Account $account, Employee $employee, float $finalTotal = 100): Salary
{
    return Salary::create([
        'date'        => '2026-01-15',
        'employee_id' => $employee->id,
        'account_id'  => $account->id,
        'month'       => 1,
        'year'        => 2026,
        'final_total' => $finalTotal,
    ]);
}

function accountBalance(Account $account): float
{
    return (float) $account->fresh()->current_balance;
}

beforeEach(function () {
    $this->account  = Account::factory()->create(['current_balance' => 1000]);
    $this->employee = Employee::factory()->create();
});

it('re-deducts the account balance when a trashed salary is restored', function () {
    $salary = makeSalary($this->account, $this->employee, 100);

    // created → salary paid out: 1000 - 100 = 900
    expect(accountBalance($this->account))->toBe(900.0);

    $salary->delete();
    // deleted → money returned: 900 + 100 = 1000
    expect(accountBalance($this->account))->toBe(1000.0);

    $salary->restore();
    // restored → salary active again, money paid out again: 1000 - 100 = 900
    expect(accountBalance($this->account))->toBe(900.0);
});

it('does not credit the account again when a trashed salary is force deleted', function () {
    $salary = makeSalary($this->account, $this->employee, 100);
    $salary->delete();

    // soft delete already returned the balance: back to 1000
    expect(accountBalance($this->account))->toBe(1000.0);

    $salary->forceDelete();
    // force deleting an already-trashed salary must NOT return the balance a second time
    expect(accountBalance($this->account))->toBe(1000.0);
});
