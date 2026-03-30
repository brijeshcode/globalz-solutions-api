<?php

use App\Models\Accounts\AccountTransfer;
use Tests\Feature\Accounts\AccountTransfers\Concerns\HasAccountTransferSetup;

uses(HasAccountTransferSetup::class);

beforeEach(function () {
    $this->setUpAccountTransfers();
});

it('creates an account transfer with minimum required fields', function () {
    $this->postJson(route('accounts.transfers.store'), $this->transferPayload())
        ->assertCreated()
        ->assertJsonStructure(['message', 'data' => ['id', 'date', 'code', 'prefix', 'from_account', 'to_account', 'from_currency', 'to_currency', 'received_amount', 'sent_amount', 'currency_rate']]);

    $this->assertDatabaseHas('account_transfers', [
        'from_account_id' => $this->fromAccount->id,
        'to_account_id'   => $this->toAccount->id,
        'received_amount' => 1000.00,
        'sent_amount'     => 950.00,
    ]);
});

it('auto-generates code with TRF prefix', function () {
    $this->postJson(route('accounts.transfers.store'), $this->transferPayload())->assertCreated();

    $transfer = AccountTransfer::where('from_account_id', $this->fromAccount->id)->latest('id')->first();

    expect($transfer->code)->not()->toBeNull()
        ->and($transfer->prefix)->toBe('TRF');
});

it('creates an account transfer with all fields including note', function () {
    $this->postJson(route('accounts.transfers.store'), $this->transferPayload(['note' => 'Complete transfer with all fields']))
        ->assertCreated()
        ->assertJson(['data' => ['note' => 'Complete transfer with all fields', 'prefix' => 'TRF']]);

    $this->assertDatabaseHas('account_transfers', ['note' => 'Complete transfer with all fields', 'prefix' => 'TRF']);
});

it('validates required fields', function () {
    $this->postJson(route('accounts.transfers.store'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['date', 'from_account_id', 'to_account_id', 'from_currency_id', 'to_currency_id', 'received_amount', 'sent_amount', 'currency_rate']);
});

it('validates foreign key references', function () {
    $this->postJson(route('accounts.transfers.store'), [
        'date'             => '2025-11-14 10:00:00',
        'from_account_id'  => 99999,
        'to_account_id'    => 99999,
        'from_currency_id' => 99999,
        'to_currency_id'   => 99999,
        'received_amount'  => 1000.00,
        'sent_amount'      => 950.00,
        'currency_rate'    => 0.95,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['from_account_id', 'to_account_id', 'from_currency_id', 'to_currency_id']);
});

it('validates amounts must be positive', function () {
    $this->postJson(route('accounts.transfers.store'), $this->transferPayload(['received_amount' => -1000.00, 'sent_amount' => -500.00]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['received_amount', 'sent_amount']);
});

it('validates currency_rate must be positive', function () {
    $this->postJson(route('accounts.transfers.store'), $this->transferPayload(['currency_rate' => -1.5]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['currency_rate']);
});

it('validates date format', function () {
    $this->postJson(route('accounts.transfers.store'), $this->transferPayload(['date' => 'invalid-date']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['date']);
});

it('validates from_account and to_account cannot be the same', function () {
    $this->postJson(route('accounts.transfers.store'), $this->transferPayload([
        'from_account_id' => $this->fromAccount->id,
        'to_account_id'   => $this->fromAccount->id,
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['to_account_id']);
});

it('validates note max length', function () {
    $this->postJson(route('accounts.transfers.store'), $this->transferPayload(['note' => str_repeat('a', 65536)]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['note']);
});
