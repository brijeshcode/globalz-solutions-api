<?php

namespace Tests\Feature\Accounts\Accounts\Concerns;

use App\Models\Setups\Accounts\AccountType;
use App\Models\Setups\Generals\Currencies\Currency;
use App\Models\User;

trait HasAccountSetup
{
    protected User $admin;
    protected AccountType $accountType;
    protected Currency $currency;

    public function setUpAccounts(): void
    {
        $this->admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $this->actingAs($this->admin, 'sanctum');

        $this->accountType = AccountType::factory()->create();
        $this->currency    = Currency::factory()->create();
    }

    protected function accountPayload(array $overrides = []): array
    {
        return array_merge([
            'name'            => 'Test Account',
            'account_type_id' => $this->accountType->id,
            'currency_id'     => $this->currency->id,
            'description'     => 'Test account description',
            'is_active'       => true,
        ], $overrides);
    }
}
