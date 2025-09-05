<?php

namespace Database\Factories\Accounts;

use App\Models\Setups\Generals\Currencies\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Accounts\Account>
 */
class AccountFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $accountNames = [
            'Cash',
            'Accounts Receivable',
            'Inventory',
            'Accounts Payable',
            'Sales Revenue',
            'Office Supplies',
            'Equipment',
            'Accumulated Depreciation',
            'Rent Expense',
            'Utilities Expense',
            'Insurance Expense',
            'Marketing Expense',
            'Travel Expense',
            'Professional Fees',
            'Interest Income',
            'Interest Expense',
            'Petty Cash',
            'Bank Account - Checking',
            'Bank Account - Savings',
            'Credit Card Payable'
        ];

        return [
            'name' => $this->faker->unique()->randomElement($accountNames),
            'account_type_id' => \App\Models\Setups\Accounts\AccountType::factory(),
            'currency_id' => Currency::factory(),
            'description' => $this->faker->optional(0.8)->sentence(),
            'is_active' => $this->faker->boolean(85),
        ];
    }
}
