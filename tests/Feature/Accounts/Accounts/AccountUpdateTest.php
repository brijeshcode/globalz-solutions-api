<?php

use App\Models\Accounts\Account;
use App\Models\Setups\Accounts\AccountType;
use App\Models\Setups\Generals\Currencies\Currency;
use Tests\Feature\Accounts\Accounts\Concerns\HasAccountSetup;

uses(HasAccountSetup::class);

beforeEach(function () {
    $this->setUpAccounts();
});

it('updates an account', function () {
    $account        = Account::factory()->create(['account_type_id' => $this->accountType->id, 'currency_id' => $this->currency->id]);
    $newAccountType = AccountType::factory()->create();
    $newCurrency    = Currency::factory()->create();

    $this->putJson(route('accounts.update', $account), [
        'name'            => 'Updated Account',
        'account_type_id' => $newAccountType->id,
        'currency_id'     => $newCurrency->id,
        'description'     => 'Updated description',
        'is_active'       => false,
    ])
        ->assertOk()
        ->assertJson(['data' => ['name' => 'Updated Account', 'description' => 'Updated description', 'is_active' => false]]);

    $this->assertDatabaseHas('accounts', [
        'id'              => $account->id,
        'name'            => 'Updated Account',
        'account_type_id' => $newAccountType->id,
        'currency_id'     => $newCurrency->id,
        'description'     => 'Updated description',
        'is_active'       => false,
    ]);
});

it('allows updating with the same name', function () {
    $account = Account::factory()->create(['name' => 'Same Name Account', 'account_type_id' => $this->accountType->id, 'currency_id' => $this->currency->id]);

    $this->putJson(route('accounts.update', $account), $this->accountPayload([
        'name'        => 'Same Name Account',
        'description' => 'Updated description',
    ]))->assertOk();

    $this->assertDatabaseHas('accounts', ['id' => $account->id, 'name' => 'Same Name Account', 'description' => 'Updated description']);
});

it('validates unique name on update', function () {
    $account1 = Account::factory()->create(['name' => 'Account One', 'account_type_id' => $this->accountType->id, 'currency_id' => $this->currency->id]);
    $account2 = Account::factory()->create(['name' => 'Account Two', 'account_type_id' => $this->accountType->id, 'currency_id' => $this->currency->id]);

    $this->putJson(route('accounts.update', $account2), $this->accountPayload(['name' => 'Account One']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

it('tracks updated_by after update', function () {
    $account = Account::factory()->create(['account_type_id' => $this->accountType->id, 'currency_id' => $this->currency->id]);

    $account->update(['name' => 'Updated Account']);

    expect($account->fresh()->updated_by)->toBe($this->admin->id);
});
