<?php

namespace Tests\Feature\Accounts\AccountAdjusts\Concerns;

use App\Models\Accounts\Account;
use App\Models\Setting;
use App\Models\User;

trait HasAccountAdjustSetup
{
    protected User $admin;
    protected Account $account;

    public function setUpAccountAdjusts(): void
    {
        $this->admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $this->actingAs($this->admin, 'sanctum');

        Setting::create([
            'group_name'  => 'account_adjusts',
            'key_name'    => 'code_counter',
            'value'       => '1000',
            'data_type'   => 'number',
            'description' => 'Account adjust code counter',
        ]);

        $this->account = Account::factory()->create();
    }

    protected function adjustPayload(array $overrides = []): array
    {
        return array_merge([
            'date'       => '2025-08-31',
            'type'       => 'Credit',
            'account_id' => $this->account->id,
            'amount'     => 500.00,
        ], $overrides);
    }
}
