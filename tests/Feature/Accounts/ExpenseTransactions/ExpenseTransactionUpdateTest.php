<?php

use Tests\Feature\Accounts\ExpenseTransactions\Concerns\HasExpenseTransactionSetup;

uses(HasExpenseTransactionSetup::class);

beforeEach(function () {
    $this->setUpExpenseTransactions();
});

it('updates an expense transaction', function () {
    $transaction  = $this->createTransaction();
    $originalCode = $transaction->code;

    $this->putJson(route('expense-transactions.update', $transaction), $this->transactionPayload([
        'date'    => '2025-09-01',
        'subject' => 'Updated Expense',
        'amount'  => 3500.00,
        'note'    => 'Updated expense transaction',
    ]))
        ->assertOk()
        ->assertJson(['data' => ['code' => $originalCode, 'subject' => 'Updated Expense', 'amount' => 3500.00]]);

    $this->assertDatabaseHas('expense_transactions', [
        'id'      => $transaction->id,
        'code'    => str_replace('EXP', '', $originalCode),
        'subject' => 'Updated Expense',
        'amount'  => 3500.00,
    ]);
});

it('code is preserved after update', function () {
    $transaction  = $this->createTransaction();
    $originalCode = $transaction->code;

    $this->putJson(route('expense-transactions.update', $transaction), $this->transactionPayload(['code' => '99999']))
        ->assertOk();

    expect($transaction->fresh()->code)->toBe($originalCode)
        ->and($transaction->fresh()->code)->not()->toBe('99999');
});

it('tracks updated_by after update', function () {
    $transaction = $this->createTransaction();
    $transaction->update(['subject' => 'Updated Subject']);

    expect($transaction->fresh()->updated_by)->toBe($this->admin->id);
});

it('updates vat_amount and recalculates total_amount', function () {
    $transaction = $this->createTransaction(['amount' => 1000.00, 'vat_amount' => 100.00]);

    $this->putJson(route('expense-transactions.update', $transaction), $this->transactionPayload([
        'amount'     => 1000.00,
        'vat_amount' => 200.00,
    ]))
        ->assertOk()
        ->assertJson(['data' => [
            'amount'       => 1000.00,
            'vat_amount'   => 200.00,
            'total_amount' => 1200.00,
        ]]);

    $this->assertDatabaseHas('expense_transactions', [
        'id'         => $transaction->id,
        'vat_amount' => 200.00,
    ]);
});

it('paid_amount syncs with vat when updated in legacy mode', function () {
    $transaction = $this->createTransaction(['amount' => 1000.00, 'vat_amount' => 0]);

    $this->putJson(route('expense-transactions.update', $transaction), $this->transactionPayload([
        'amount'     => 1000.00,
        'vat_amount' => 150.00,
    ]))->assertOk();

    $this->assertDatabaseHas('expense_transactions', [
        'id'          => $transaction->id,
        'paid_amount' => 1150.00,
    ]);
});
