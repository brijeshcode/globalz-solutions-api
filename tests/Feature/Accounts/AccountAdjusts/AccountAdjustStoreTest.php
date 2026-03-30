<?php

use App\Models\Accounts\AccountAdjust;
use Tests\Feature\Accounts\AccountAdjusts\Concerns\HasAccountAdjustSetup;

uses(HasAccountAdjustSetup::class);

beforeEach(function () {
    $this->setUpAccountAdjusts();
});

it('creates a Credit adjustment', function () {
    $this->postJson(route('accounts.adjusts.store'), $this->adjustPayload(['type' => 'Credit', 'amount' => 1000.00]))
        ->assertCreated()
        ->assertJsonStructure(['message', 'data' => ['id', 'code', 'prefix', 'transfer_code', 'type', 'amount', 'account']])
        ->assertJson(['data' => ['type' => 'Credit', 'amount' => 1000.00, 'prefix' => 'ADJ']]);

    $this->assertDatabaseHas('account_adjusts', ['type' => 'Credit', 'amount' => '1000.00', 'account_id' => $this->account->id]);
});

it('creates a Debit adjustment', function () {
    $this->postJson(route('accounts.adjusts.store'), $this->adjustPayload(['type' => 'Debit', 'amount' => 250.00]))
        ->assertCreated()
        ->assertJson(['data' => ['type' => 'Debit', 'amount' => 250.00]]);

    $this->assertDatabaseHas('account_adjusts', ['type' => 'Debit', 'amount' => '250.00']);
});

it('auto-generates code with ADJ prefix', function () {
    $this->postJson(route('accounts.adjusts.store'), $this->adjustPayload())->assertCreated();

    $adjust = AccountAdjust::latest('id')->first();
    expect($adjust->code)->not()->toBeNull()
        ->and($adjust->prefix)->toBe('ADJ')
        ->and($adjust->transfer_code)->toStartWith('ADJ');
});

it('creates adjustment with optional note', function () {
    $this->postJson(route('accounts.adjusts.store'), $this->adjustPayload(['note' => 'Quarterly balance correction']))
        ->assertCreated()
        ->assertJson(['data' => ['note' => 'Quarterly balance correction']]);
});

it('sets created_by and updated_by automatically', function () {
    $this->postJson(route('accounts.adjusts.store'), $this->adjustPayload())->assertCreated();

    $adjust = AccountAdjust::latest('id')->first();
    expect($adjust->created_by)->toBe($this->admin->id)
        ->and($adjust->updated_by)->toBe($this->admin->id);
});

it('validates required fields', function () {
    $this->postJson(route('accounts.adjusts.store'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['date', 'type', 'account_id', 'amount']);
});

it('validates type must be Credit or Debit', function () {
    $this->postJson(route('accounts.adjusts.store'), $this->adjustPayload(['type' => 'Invalid']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['type']);
});

it('validates amount must be greater than zero', function () {
    $this->postJson(route('accounts.adjusts.store'), $this->adjustPayload(['amount' => 0]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['amount']);
});

it('validates amount must be numeric', function () {
    $this->postJson(route('accounts.adjusts.store'), $this->adjustPayload(['amount' => 'not-a-number']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['amount']);
});

it('validates account_id must exist', function () {
    $this->postJson(route('accounts.adjusts.store'), $this->adjustPayload(['account_id' => 99999]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['account_id']);
});

it('validates date format', function () {
    $this->postJson(route('accounts.adjusts.store'), $this->adjustPayload(['date' => 'not-a-date']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['date']);
});

it('validates note max length', function () {
    $this->postJson(route('accounts.adjusts.store'), $this->adjustPayload(['note' => str_repeat('a', 1001)]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['note']);
});
