<?php

use App\Models\Accounts\IncomeTransaction;
use Tests\Feature\Accounts\IncomeTransactions\Concerns\HasIncomeTransactionSetup;

uses(HasIncomeTransactionSetup::class);

beforeEach(function () {
    $this->setUpIncomeTransactions();
});

it('creates an income transaction with minimum required fields', function () {
    $this->postJson(route('income-transactions.store'), [
        'date'               => '2025-08-31',
        'income_category_id' => $this->incomeCategory->id,
        'account_id'         => $this->account->id,
        'amount'             => 500.00,
    ])
        ->assertCreated()
        ->assertJsonStructure(['message', 'data' => ['id', 'date', 'code', 'amount', 'income_category', 'account']]);

    $this->assertDatabaseHas('income_transactions', [
        'income_category_id' => $this->incomeCategory->id,
        'account_id'         => $this->account->id,
        'amount'             => '500.00',
    ]);
});

it('creates an income transaction with all fields', function () {
    $this->postJson(route('income-transactions.store'), $this->transactionPayload([
        'subject'         => 'Complete Income Transaction',
        'amount'          => 2500.75,
        'order_number'    => 'ORD-12345',
        'check_number'    => 'CHK-67890',
        'bank_ref_number' => 'BNK-98765',
        'note'            => 'Complete income transaction with all fields',
    ]))
        ->assertCreated()
        ->assertJson(['data' => [
            'subject'         => 'Complete Income Transaction',
            'amount'          => 2500.75,
            'order_number'    => 'ORD-12345',
            'check_number'    => 'CHK-67890',
            'bank_ref_number' => 'BNK-98765',
            'note'            => 'Complete income transaction with all fields',
        ]]);

    $this->assertDatabaseHas('income_transactions', ['subject' => 'Complete Income Transaction', 'order_number' => 'ORD-12345']);
});

it('sets created_by and updated_by automatically', function () {
    $transaction = $this->createTransaction();

    expect($transaction->created_by)->toBe($this->admin->id)
        ->and($transaction->updated_by)->toBe($this->admin->id);
});

it('validates required fields', function () {
    $this->postJson(route('income-transactions.store'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['date', 'income_category_id', 'account_id', 'amount']);
});

it('validates foreign key references', function () {
    $this->postJson(route('income-transactions.store'), [
        'date'               => '2025-08-31',
        'income_category_id' => 99999,
        'account_id'         => 99999,
        'amount'             => 1000.00,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['income_category_id', 'account_id']);
});

it('validates amount must be positive', function () {
    $this->postJson(route('income-transactions.store'), $this->transactionPayload(['amount' => -500.00]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['amount']);
});

it('validates amount range', function () {
    $this->postJson(route('income-transactions.store'), $this->transactionPayload(['amount' => 9999999999999.99]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['amount']);
});

it('validates date format', function () {
    $this->postJson(route('income-transactions.store'), $this->transactionPayload(['date' => 'invalid-date']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['date']);
});

it('validates maximum length for string fields', function () {
    $this->postJson(route('income-transactions.store'), $this->transactionPayload([
        'subject'         => str_repeat('a', 201),
        'order_number'    => str_repeat('b', 101),
        'check_number'    => str_repeat('c', 101),
        'bank_ref_number' => str_repeat('d', 101),
        'note'            => str_repeat('e', 251),
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['subject', 'order_number', 'check_number', 'bank_ref_number', 'note']);
});
