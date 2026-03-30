<?php

use App\Models\Expenses\ExpenseTransaction;
use Tests\Feature\Accounts\ExpenseTransactions\Concerns\HasExpenseTransactionSetup;

uses(HasExpenseTransactionSetup::class);

beforeEach(function () {
    $this->setUpExpenseTransactions();
});

it('creates an expense transaction with minimum required fields', function () {
    $this->postJson(route('expense-transactions.store'), [
        'date'                => '2025-08-31',
        'expense_category_id' => $this->expenseCategory->id,
        'account_id'          => $this->account->id,
        'amount'              => 500.00,
    ])
        ->assertCreated()
        ->assertJsonStructure(['message', 'data' => ['id', 'date', 'code', 'amount', 'expense_category', 'account']]);

    $this->assertDatabaseHas('expense_transactions', [
        'expense_category_id' => $this->expenseCategory->id,
        'account_id'          => $this->account->id,
        'amount'              => 500.00,
    ]);
});

it('creates an expense transaction with all fields', function () {
    $this->postJson(route('expense-transactions.store'), $this->transactionPayload([
        'subject'         => 'Complete Expense Transaction',
        'amount'          => 2500.75,
        'order_number'    => 'ORD-12345',
        'check_number'    => 'CHK-67890',
        'bank_ref_number' => 'BNK-98765',
        'note'            => 'Complete expense transaction with all fields',
    ]))
        ->assertCreated()
        ->assertJson(['data' => [
            'subject'         => 'Complete Expense Transaction',
            'amount'          => 2500.75,
            'order_number'    => 'ORD-12345',
            'check_number'    => 'CHK-67890',
            'bank_ref_number' => 'BNK-98765',
            'note'            => 'Complete expense transaction with all fields',
        ]]);

    $this->assertDatabaseHas('expense_transactions', ['subject' => 'Complete Expense Transaction', 'order_number' => 'ORD-12345']);
});

it('sets created_by and updated_by automatically', function () {
    $transaction = $this->createTransaction();

    expect($transaction->created_by)->toBe($this->admin->id)
        ->and($transaction->updated_by)->toBe($this->admin->id);
});

it('validates required fields', function () {
    $this->postJson(route('expense-transactions.store'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['date', 'expense_category_id', 'account_id', 'amount']);
});

it('validates foreign key references', function () {
    $this->postJson(route('expense-transactions.store'), [
        'date'                => '2025-08-31',
        'expense_category_id' => 99999,
        'account_id'          => 99999,
        'amount'              => 1000.00,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['expense_category_id', 'account_id']);
});

it('validates amount must be positive', function () {
    $this->postJson(route('expense-transactions.store'), $this->transactionPayload(['amount' => -500.00]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['amount']);
});

it('validates amount range', function () {
    $this->postJson(route('expense-transactions.store'), $this->transactionPayload(['amount' => 9999999999999.99]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['amount']);
});

it('validates date format', function () {
    $this->postJson(route('expense-transactions.store'), $this->transactionPayload(['date' => 'invalid-date']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['date']);
});

it('validates maximum length for string fields', function () {
    $this->postJson(route('expense-transactions.store'), $this->transactionPayload([
        'subject'         => str_repeat('a', 201),
        'order_number'    => str_repeat('b', 101),
        'check_number'    => str_repeat('c', 101),
        'bank_ref_number' => str_repeat('d', 101),
        'note'            => str_repeat('e', 251),
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['subject', 'order_number', 'check_number', 'bank_ref_number', 'note']);
});
