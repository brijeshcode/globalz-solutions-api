<?php

namespace Tests\Feature\Accounts\ExpenseTransactions\Concerns;

use App\Models\Accounts\Account;
use App\Models\Setting;
use App\Models\Setups\Expenses\ExpenseCategory;
use App\Models\User;

trait HasExpenseTransactionSetup
{
    protected User $admin;
    protected ExpenseCategory $expenseCategory;
    protected Account $account;

    public function setUpExpenseTransactions(): void
    {
        $this->admin = User::factory()->create();
        $this->actingAs($this->admin, 'sanctum');

        Setting::create([
            'group_name'  => 'expense_transactions',
            'key_name'    => 'code_counter',
            'value'       => '100',
            'data_type'   => 'number',
            'description' => 'Expense transaction code counter starting from 100',
        ]);

        $this->expenseCategory = ExpenseCategory::factory()->create();
        $this->account         = Account::factory()->create();
    }

    protected function transactionPayload(array $overrides = []): array
    {
        return array_merge([
            'date'                => '2025-08-31',
            'expense_category_id' => $this->expenseCategory->id,
            'account_id'          => $this->account->id,
            'subject'             => 'Test Expense',
            'amount'              => 1500.00,
        ], $overrides);
    }

    protected function createTransaction(array $overrides = [])
    {
        return \App\Models\Expenses\ExpenseTransaction::factory()->create(array_merge([
            'expense_category_id' => $this->expenseCategory->id,
            'account_id'          => $this->account->id,
        ], $overrides));
    }
}
