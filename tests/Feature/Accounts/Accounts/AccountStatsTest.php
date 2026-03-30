<?php

use App\Models\Accounts\Account;
use Tests\Feature\Accounts\Accounts\Concerns\HasAccountSetup;

uses(HasAccountSetup::class);

beforeEach(function () {
    $this->setUpAccounts();
});

it('returns account statistics with correct structure', function () {
    Account::factory()->count(5)->create(['is_active' => true, 'account_type_id' => $this->accountType->id, 'currency_id' => $this->currency->id]);
    Account::factory()->count(2)->create(['is_active' => false, 'account_type_id' => $this->accountType->id, 'currency_id' => $this->currency->id]);

    $stats = $this->getJson(route('accounts.stats'))
        ->assertOk()
        ->assertJsonStructure(['message', 'data' => ['total_accounts', 'total_current_balance_usd', 'total_private_accounts', 'total_private_balance_usd']])
        ->json('data');

    expect($stats['total_accounts'])->toBeGreaterThanOrEqual(0)
        ->and($stats['total_current_balance_usd'])->toBeNumeric();
});
