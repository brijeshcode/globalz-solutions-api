<?php

namespace Database\Factories\Setups\Accounts;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Setups\Accounts\AccountType>
 */
class AccountTypeFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $accountTypes = [
            'Cash',
            'Bank',
            'Other',
            'Assets',
            'Liabilities',
            'Equity',
            'Revenue',
            'Expenses',
            'Current Assets',
            'Fixed Assets',
            'Current Liabilities',
            'Long-term Liabilities',
            'Operating Revenue',
            'Other Revenue',
            'Operating Expenses',
            'Other Expenses',
            'Cost of Goods Sold',
            'Administrative Expenses'
        ];

        return [
            'name' => $this->faker->unique()->randomElement($accountTypes),
            'description' => $this->faker->optional(0.7)->sentence(),
            'is_active' => $this->faker->boolean(90),
        ];
    }
}
