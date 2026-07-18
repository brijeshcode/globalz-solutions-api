<?php

use App\Contracts\ModuleLockable;
use App\Models\Customers\CustomerCreditDebitNote;
use App\Models\Customers\CustomerPayment;
use App\Models\Customers\CustomerReturn;
use App\Models\Customers\Sale;
use App\Models\Expenses\ExpensePayment;
use App\Models\Expenses\ExpenseTransaction;
use App\Models\Suppliers\Purchase;
use App\Models\Suppliers\SupplierCreditDebitNote;
use App\Models\Suppliers\SupplierPayment;

it('implements ModuleLockable on every lockable model', function (string $class) {
    expect(new $class())->toBeInstanceOf(ModuleLockable::class);
})->with([
    Sale::class,
    Purchase::class,
    CustomerPayment::class,
    CustomerReturn::class,
    CustomerCreditDebitNote::class,
    SupplierCreditDebitNote::class,
    SupplierPayment::class,
    ExpenseTransaction::class,
    ExpensePayment::class,
]);

it('uses the sale key when approved and the sale_order key when pending', function () {
    $approved = new Sale(['approved_by' => 1]);
    $pending  = new Sale();

    expect($approved->moduleLockKey())->toBe('sale')
        ->and($pending->moduleLockKey())->toBe('sale_order');
});

it('exempts an approved sale until it is delivered', function () {
    $waiting   = new Sale(['approved_by' => 1, 'status' => 'Waiting']);
    $shipped   = new Sale(['approved_by' => 1, 'status' => 'Shipped']);
    $delivered = new Sale(['approved_by' => 1, 'status' => 'Delivered']);
    $pending   = new Sale(['status' => 'Waiting']);

    expect($waiting->isModuleLockExempt())->toBeTrue()
        ->and($shipped->isModuleLockExempt())->toBeTrue()
        ->and($delivered->isModuleLockExempt())->toBeFalse()
        ->and($pending->isModuleLockExempt())->toBeFalse(); // orders lock by age alone
});

it('exempts a purchase until it is delivered', function () {
    $waiting   = new Purchase(['status' => 'Waiting']);
    $delivered = new Purchase(['status' => 'Delivered']);

    expect($waiting->isModuleLockExempt())->toBeTrue()
        ->and($delivered->isModuleLockExempt())->toBeFalse()
        ->and($waiting->moduleLockKey())->toBe('purchase');
});

it('uses payment vs payment_order key and never exempts customer payments', function () {
    $approved = new CustomerPayment(['approved_by' => 1]);
    $pending  = new CustomerPayment();

    expect($approved->moduleLockKey())->toBe('customer_payment')
        ->and($pending->moduleLockKey())->toBe('customer_payment_order')
        ->and($approved->isModuleLockExempt())->toBeFalse()
        ->and($pending->isModuleLockExempt())->toBeFalse();
});

it('uses return vs return_order key and exempts approved returns until received', function () {
    $received   = new CustomerReturn(['approved_by' => 1, 'return_received_by' => 1]);
    $unreceived = new CustomerReturn(['approved_by' => 1]);
    $pending    = new CustomerReturn();

    expect($received->moduleLockKey())->toBe('customer_return')
        ->and($pending->moduleLockKey())->toBe('customer_return_order')
        ->and($unreceived->isModuleLockExempt())->toBeTrue()
        ->and($received->isModuleLockExempt())->toBeFalse()
        ->and($pending->isModuleLockExempt())->toBeFalse();
});

it('exempts an expense until any payment has been made', function () {
    $unpaid  = new ExpenseTransaction(['paid_amount' => 0]);
    $partial = new ExpenseTransaction(['paid_amount' => 10]);

    expect($unpaid->isModuleLockExempt())->toBeTrue()
        ->and($partial->isModuleLockExempt())->toBeFalse()
        ->and($unpaid->moduleLockKey())->toBe('expense');
});

it('never exempts the instant-final documents', function (ModuleLockable $record, string $key) {
    expect($record->isModuleLockExempt())->toBeFalse()
        ->and($record->moduleLockKey())->toBe($key);
})->with([
    fn () => [new CustomerCreditDebitNote(), 'customer_credit_note'],
    fn () => [new SupplierCreditDebitNote(), 'supplier_credit_note'],
    fn () => [new SupplierPayment(), 'supplier_payment'],
    fn () => [new ExpensePayment(), 'expense_payment'],
]);

it('returns the document date as the lock date', function () {
    $sale = new Sale(['date' => '2026-01-15']);

    expect($sale->moduleLockDate()->toDateString())->toBe('2026-01-15');
});
