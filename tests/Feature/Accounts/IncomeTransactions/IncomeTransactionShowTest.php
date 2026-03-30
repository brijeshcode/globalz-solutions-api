<?php

use Tests\Feature\Accounts\IncomeTransactions\Concerns\HasIncomeTransactionSetup;

uses(HasIncomeTransactionSetup::class);

beforeEach(function () {
    $this->setUpIncomeTransactions();
});

it('shows an income transaction', function () {
    $transaction = $this->createTransaction();

    $this->getJson(route('income-transactions.show', $transaction))
        ->assertOk()
        ->assertJson(['data' => [
            'id'     => $transaction->id,
            'code'   => $transaction->code,
            'date'   => $transaction->date->format('Y-m-d'),
            'amount' => $transaction->amount,
        ]]);
});

it('returns 404 for a non-existent income transaction', function () {
    $this->getJson(route('income-transactions.show', 999))->assertNotFound();
});
