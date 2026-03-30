<?php

use Tests\Feature\Accounts\ExpenseTransactions\Concerns\HasExpenseTransactionSetup;

uses(HasExpenseTransactionSetup::class);

beforeEach(function () {
    $this->setUpExpenseTransactions();
});

it('shows an expense transaction', function () {
    $transaction = $this->createTransaction();

    $this->getJson(route('expense-transactions.show', $transaction))
        ->assertOk()
        ->assertJson(['data' => [
            'id'     => $transaction->id,
            'code'   => $transaction->code,
            'date'   => $transaction->date->format('Y-m-d'),
            'amount' => $transaction->amount,
        ]]);
});

it('returns 404 for a non-existent expense transaction', function () {
    $this->getJson(route('expense-transactions.show', 999))->assertNotFound();
});
