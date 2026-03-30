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
        ]])
        ->assertJsonStructure(['data' => [
            'vat_amount',
            'vat_amount_usd',
            'total_amount',
            'total_amount_usd',
        ]]);
});

it('shows correct vat and total fields when vat is set', function () {
    $transaction = $this->createTransaction(['amount' => 500.00, 'vat_amount' => 50.00]);

    $this->getJson(route('expense-transactions.show', $transaction))
        ->assertOk()
        ->assertJson(['data' => [
            'amount'       => 500.00,
            'vat_amount'   => 50.00,
            'total_amount' => 550.00,
        ]]);
});

it('returns 404 for a non-existent expense transaction', function () {
    $this->getJson(route('expense-transactions.show', 999))->assertNotFound();
});
