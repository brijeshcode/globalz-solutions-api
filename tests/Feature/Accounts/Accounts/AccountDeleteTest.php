<?php

use App\Models\Accounts\Account;
use Tests\Feature\Accounts\Accounts\Concerns\HasAccountSetup;

uses(HasAccountSetup::class);

beforeEach(function () {
    $this->setUpAccounts();
});

it('soft deletes an account', function () {
    $account = Account::factory()->create(['account_type_id' => $this->accountType->id, 'currency_id' => $this->currency->id]);

    $this->deleteJson(route('accounts.destroy', $account))->assertNoContent();

    $this->assertSoftDeleted('accounts', ['id' => $account->id]);
});

it('lists trashed accounts', function () {
    $account = Account::factory()->create(['account_type_id' => $this->accountType->id, 'currency_id' => $this->currency->id]);
    $account->delete();

    $this->getJson(route('accounts.trashed'))
        ->assertOk()
        ->assertJsonStructure(['message', 'data' => ['*' => ['id', 'name', 'account_type', 'currency', 'is_active']], 'pagination'])
        ->assertJsonCount(1, 'data');
});

it('restores a trashed account', function () {
    $account = Account::factory()->create(['account_type_id' => $this->accountType->id, 'currency_id' => $this->currency->id]);
    $account->delete();

    $this->patchJson(route('accounts.restore', $account->id))->assertOk();

    $this->assertDatabaseHas('accounts', ['id' => $account->id, 'deleted_at' => null]);
});

it('force deletes a trashed account', function () {
    $account = Account::factory()->create(['account_type_id' => $this->accountType->id, 'currency_id' => $this->currency->id]);
    $account->delete();

    $this->deleteJson(route('accounts.force-delete', $account->id))->assertNoContent();

    $this->assertDatabaseMissing('accounts', ['id' => $account->id]);
});
