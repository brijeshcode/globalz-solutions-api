<?php

use Tests\Feature\Accounts\ExpenseTransactions\Concerns\HasExpenseTransactionSetup;

uses(HasExpenseTransactionSetup::class);

beforeEach(function () {
    $this->setUpExpenseTransactions();
});

it('soft deletes an expense transaction', function () {
    $transaction = $this->createTransaction();

    $this->deleteJson(route('expense-transactions.destroy', $transaction))->assertStatus(204);

    $this->assertSoftDeleted('expense_transactions', ['id' => $transaction->id]);
});

it('lists trashed expense transactions', function () {
    $transaction = $this->createTransaction();
    $transaction->delete();

    $this->getJson(route('expense-transactions.trashed'))
        ->assertOk()
        ->assertJsonStructure(['message', 'data' => ['*' => ['id', 'date', 'code', 'subject', 'amount']], 'pagination'])
        ->assertJsonCount(1, 'data');
});

it('restores a trashed expense transaction', function () {
    $transaction = $this->createTransaction();
    $transaction->delete();

    $this->patchJson(route('expense-transactions.restore', $transaction->id))->assertOk();

    $this->assertDatabaseHas('expense_transactions', ['id' => $transaction->id, 'deleted_at' => null]);
});

it('force deletes a trashed expense transaction', function () {
    $transaction = $this->createTransaction();
    $transaction->delete();

    $this->deleteJson(route('expense-transactions.force-delete', $transaction->id))->assertStatus(204);

    $this->assertDatabaseMissing('expense_transactions', ['id' => $transaction->id]);
});
