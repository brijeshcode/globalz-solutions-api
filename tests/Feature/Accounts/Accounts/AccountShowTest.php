<?php

use App\Models\Accounts\Account;
use Tests\Feature\Accounts\Accounts\Concerns\HasAccountSetup;

uses(HasAccountSetup::class);

beforeEach(function () {
    $this->setUpAccounts();
});

it('shows an account', function () {
    $account = Account::factory()->create([
        'account_type_id' => $this->accountType->id,
        'currency_id'     => $this->currency->id,
    ]);

    $this->getJson(route('accounts.show', $account))
        ->assertOk()
        ->assertJson(['data' => ['id' => $account->id, 'name' => $account->name, 'description' => $account->description]]);
});

it('returns 404 for a non-existent account', function () {
    $this->getJson(route('accounts.show', 999))->assertNotFound();
});
