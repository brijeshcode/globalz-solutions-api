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
        'code'    => $originalCode,
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
