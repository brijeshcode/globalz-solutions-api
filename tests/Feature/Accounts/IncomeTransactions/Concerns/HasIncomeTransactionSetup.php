<?php

namespace Tests\Feature\Accounts\IncomeTransactions\Concerns;

use App\Models\Accounts\Account;
use App\Models\Setting;
use App\Models\Setups\Accounts\IncomeCategory;
use App\Models\User;

trait HasIncomeTransactionSetup
{
    protected User $admin;
    protected IncomeCategory $incomeCategory;
    protected Account $account;

    public function setUpIncomeTransactions(): void
    {
        $this->admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $this->actingAs($this->admin, 'sanctum');

        Setting::create([
            'group_name'  => 'income_transactions',
            'key_name'    => 'code_counter',
            'value'       => '100',
            'data_type'   => 'number',
            'description' => 'Income transaction code counter starting from 100',
        ]);

        $this->incomeCategory = IncomeCategory::factory()->create();
        $this->account        = Account::factory()->create();
    }

    protected function transactionPayload(array $overrides = []): array
    {
        return array_merge([
            'date'               => '2025-08-31',
            'income_category_id' => $this->incomeCategory->id,
            'account_id'         => $this->account->id,
            'subject'            => 'Test Income',
            'amount'             => 1500.00,
        ], $overrides);
    }

    protected function createTransaction(array $overrides = [])
    {
        return \App\Models\Accounts\IncomeTransaction::factory()->create(array_merge([
            'income_category_id' => $this->incomeCategory->id,
            'account_id'         => $this->account->id,
        ], $overrides));
    }
}
