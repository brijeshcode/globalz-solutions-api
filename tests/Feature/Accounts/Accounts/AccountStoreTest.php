<?php

use App\Models\Accounts\Account;
use Tests\Feature\Accounts\Accounts\Concerns\HasAccountSetup;

uses(HasAccountSetup::class);

beforeEach(function () {
    $this->setUpAccounts();
});

it('creates an account with minimum required fields', function () {
    $this->postJson(route('accounts.store'), [
        'name'            => 'Test Cash Account',
        'account_type_id' => $this->accountType->id,
        'currency_id'     => $this->currency->id,
        'is_active'       => true,
    ])
        ->assertCreated()
        ->assertJsonStructure(['message', 'data' => ['id', 'name', 'account_type', 'currency', 'is_active']]);

    $this->assertDatabaseHas('accounts', [
        'name'            => 'Test Cash Account',
        'account_type_id' => $this->accountType->id,
        'currency_id'     => $this->currency->id,
        'is_active'       => true,
    ]);
});

it('creates an account with all fields', function () {
    $this->postJson(route('accounts.store'), $this->accountPayload([
        'name'            => 'Complete Account',
        'opening_balance' => 1500,
        'description'     => 'A complete test account with all fields',
    ]))
        ->assertCreated()
        ->assertJson(['data' => ['name' => 'Complete Account', 'description' => 'A complete test account with all fields', 'is_active' => true]]);

    $this->assertDatabaseHas('accounts', ['name' => 'Complete Account', 'description' => 'A complete test account with all fields']);
});

it('sets created_by and updated_by automatically', function () {
    $account = Account::factory()->create([
        'account_type_id' => $this->accountType->id,
        'currency_id'     => $this->currency->id,
    ]);

    expect($account->created_by)->toBe($this->admin->id)
        ->and($account->updated_by)->toBe($this->admin->id);
});

it('validates required fields', function () {
    $this->postJson(route('accounts.store'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'account_type_id', 'currency_id']);
});

it('validates unique name constraint', function () {
    Account::factory()->create(['name' => 'Duplicate Account Name', 'account_type_id' => $this->accountType->id, 'currency_id' => $this->currency->id]);

    $this->postJson(route('accounts.store'), $this->accountPayload(['name' => 'Duplicate Account Name']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('validates foreign key references', function () {
    $this->postJson(route('accounts.store'), [
        'name'            => 'Test Account',
        'account_type_id' => 99999,
        'currency_id'     => 99999,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['account_type_id', 'currency_id']);
});

it('validates name max length', function () {
    $this->postJson(route('accounts.store'), $this->accountPayload(['name' => str_repeat('a', 256)]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('validates description max length', function () {
    $this->postJson(route('accounts.store'), $this->accountPayload(['description' => str_repeat('a', 65536)]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['description']);
});
