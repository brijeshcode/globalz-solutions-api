<?php

use Tests\Feature\Accounts\IncomeTransactions\Concerns\HasIncomeTransactionSetup;

uses(HasIncomeTransactionSetup::class);

beforeEach(function () {
    $this->setUpIncomeTransactions();
});

it('soft deletes an income transaction', function () {
    $transaction = $this->createTransaction();

    $this->deleteJson(route('income-transactions.destroy', $transaction))->assertStatus(204);

    $this->assertSoftDeleted('income_transactions', ['id' => $transaction->id]);
});

it('lists trashed income transactions', function () {
    $transaction = $this->createTransaction();
    $transaction->delete();

    $this->getJson(route('income-transactions.trashed'))
        ->assertOk()
        ->assertJsonStructure(['message', 'data' => ['*' => ['id', 'date', 'code', 'subject', 'amount']], 'pagination'])
        ->assertJsonCount(1, 'data');
});

it('restores a trashed income transaction', function () {
    $transaction = $this->createTransaction();
    $transaction->delete();

    $this->patchJson(route('income-transactions.restore', $transaction->id))->assertOk();

    $this->assertDatabaseHas('income_transactions', ['id' => $transaction->id, 'deleted_at' => null]);
});

it('force deletes a trashed income transaction', function () {
    $transaction = $this->createTransaction();
    $transaction->delete();

    $this->deleteJson(route('income-transactions.force-delete', $transaction->id))->assertStatus(204);

    $this->assertDatabaseMissing('income_transactions', ['id' => $transaction->id]);
});
