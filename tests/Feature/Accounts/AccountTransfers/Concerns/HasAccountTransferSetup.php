<?php

namespace Tests\Feature\Accounts\AccountTransfers\Concerns;

use App\Models\Accounts\Account;
use App\Models\Setups\Accounts\AccountType;
use App\Models\Setups\Generals\Currencies\Currency;
use App\Models\User;

trait HasAccountTransferSetup
{
    protected User $admin;
    protected AccountType $accountType;
    protected Currency $currency1;
    protected Currency $currency2;
    protected Account $fromAccount;
    protected Account $toAccount;

    public function setUpAccountTransfers(): void
    {
        $this->admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $this->actingAs($this->admin, 'sanctum');

        $this->accountType = AccountType::factory()->create();
        $this->currency1   = Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'is_active' => true]);
        $this->currency2   = Currency::firstOrCreate(['code' => 'EUR'], ['name' => 'Euro', 'symbol' => '€', 'is_active' => true]);

        $this->fromAccount = Account::factory()->create([
            'name'            => 'Source Account',
            'account_type_id' => $this->accountType->id,
            'currency_id'     => $this->currency1->id,
            'is_active'       => true,
        ]);

        $this->toAccount = Account::factory()->create([
            'name'            => 'Destination Account',
            'account_type_id' => $this->accountType->id,
            'currency_id'     => $this->currency2->id,
            'is_active'       => true,
        ]);
    }

    protected function transferPayload(array $overrides = []): array
    {
        return array_merge([
            'date'             => '2025-11-14 10:00:00',
            'from_account_id'  => $this->fromAccount->id,
            'to_account_id'    => $this->toAccount->id,
            'from_currency_id' => $this->currency1->id,
            'to_currency_id'   => $this->currency2->id,
            'received_amount'  => 1000.00,
            'sent_amount'      => 950.00,
            'currency_rate'    => 0.95,
        ], $overrides);
    }

    protected function createTransfer(array $overrides = [])
    {
        return \App\Models\Accounts\AccountTransfer::factory()->create(array_merge([
            'from_account_id'  => $this->fromAccount->id,
            'to_account_id'    => $this->toAccount->id,
            'from_currency_id' => $this->currency1->id,
            'to_currency_id'   => $this->currency2->id,
        ], $overrides));
    }
}
