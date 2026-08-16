<?php

use Tests\Feature\Accounts\ExpenseTransactions\Concerns\HasExpenseTransactionSetup;

uses(HasExpenseTransactionSetup::class);

beforeEach(function () {
    $this->setUpExpenseTransactions();
});

it('includes the total of vat_amount_usd in stats', function () {
    $this->createTransaction(['amount' => 100, 'amount_usd' => 100, 'vat_amount' => 30, 'vat_amount_usd' => 30]);
    $this->createTransaction(['amount' => 200, 'amount_usd' => 200, 'vat_amount' => 20, 'vat_amount_usd' => 20]);

    $stats = $this->getJson(route('expense-transactions.stats'))
        ->assertOk()
        ->assertJsonStructure(['message', 'data' => ['total_transactions', 'total_amount_usd', 'total_vat_amount_usd']])
        ->json('data');

    expect($stats['total_vat_amount_usd'])->toEqual(50);
});
