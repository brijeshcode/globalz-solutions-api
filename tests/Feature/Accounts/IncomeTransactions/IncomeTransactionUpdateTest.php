<?php

use App\Models\Accounts\IncomeTransaction;
use Tests\Feature\Accounts\IncomeTransactions\Concerns\HasIncomeTransactionSetup;

uses(HasIncomeTransactionSetup::class);

beforeEach(function () {
    $this->setUpIncomeTransactions();
});

it('updates an income transaction', function () {
    $transaction  = \App\Models\Accounts\IncomeTransaction::factory()->create([
        'income_category_id' => $this->incomeCategory->id,
        'account_id'         => $this->account->id,
        'currency_id'        => $this->account->currency_id,
        'currency_rate'      => 1,
    ]);
    $originalCode = $transaction->code;

    $this->putJson(route('income-transactions.update', $transaction), $this->transactionPayload([
        'date'    => '2025-09-01',
        'subject' => 'Updated Income',
        'amount'  => 3500.00,
        'note'    => 'Updated income transaction',
    ]))
        ->assertOk()
        ->assertJson(['data' => ['code' => $originalCode, 'subject' => 'Updated Income', 'amount' => 3500.00]]);

    $this->assertDatabaseHas('income_transactions', [
        'id'      => $transaction->id,
        'code'    => ltrim($originalCode, IncomeTransaction::PREFIX),
        'subject' => 'Updated Income',
        'amount'  => 3500.00,
    ]);
});

it('code is preserved after update', function () {
    $transaction  = $this->createTransaction();
    $originalCode = $transaction->code;

    $this->putJson(route('income-transactions.update', $transaction), $this->transactionPayload(['code' => '99999']))
        ->assertOk();

    expect($transaction->fresh()->code)->toBe($originalCode)
        ->and($transaction->fresh()->code)->not()->toBe('99999');
});

it('tracks updated_by after update', function () {
    $transaction = $this->createTransaction();
    $transaction->update(['subject' => 'Updated Subject']);

    expect($transaction->fresh()->updated_by)->toBe($this->admin->id);
});
