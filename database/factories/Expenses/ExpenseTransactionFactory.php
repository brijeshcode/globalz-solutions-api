<?php

namespace Database\Factories\Expenses;

use App\Models\Expenses\ExpenseTransaction;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Expenses\ExpenseTransaction>
 */
class ExpenseTransactionFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = ExpenseTransaction::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $expenseSubjects = [
            'Office Supplies Purchase',
            'Fuel Expense',
            'Marketing Campaign',
            'Equipment Maintenance',
            'Travel Feess',
            'Professional Services',
            'Utility Bills',
            'Insurance Premium',
            'Software License',
            'Training Program',
            'Telephone Bills',
            'Rent Payment',
            'Repair and Maintenance',
            'Advertising Costs',
            'Transportation Costs'
        ];

        return [
            'date' => fake()->dateTimeBetween('-1 year', 'now'),
            'expense_category_id' => \App\Models\Setups\Expenses\ExpenseCategory::factory(),
            'account_id' => \App\Models\Accounts\Account::factory(),
            'subject' => fake()->optional(0.8)->randomElement($expenseSubjects),
            'amount' => fake()->randomFloat(2, 10, 50000),
            'order_number' => fake()->optional(0.3)->numerify('ORD-####'),
            'check_number' => fake()->optional(0.2)->numerify('CHK-####'),
            'bank_ref_number' => fake()->optional(0.4)->numerify('BNK-#######'),
            'note' => fake()->optional(0.5)->sentence(),
            'created_by' => 1,
            'updated_by' => 1,
        ];
    }

    /**
     * Indicate that the transaction is for office supplies.
     */
    public function officeSupplies(): static
    {
        return $this->state(fn (array $attributes) => [
            'subject' => 'Office Supplies Purchase',
            'amount' => fake()->randomFloat(2, 50, 2000),
            'note' => 'Purchase of various office supplies including stationery, paper, and other consumables.',
        ]);
    }

    /**
     * Indicate that the transaction is for travel expenses.
     */
    public function travelExpense(): static
    {
        return $this->state(fn (array $attributes) => [
            'subject' => 'Business Travel Expense',
            'amount' => fake()->randomFloat(2, 200, 5000),
            'note' => 'Business travel expenses including accommodation, meals, and transportation.',
        ]);
    }

    /**
     * Indicate that the transaction is a large expense.
     */
    public function largeAmount(): static
    {
        return $this->state(fn (array $attributes) => [
            'amount' => fake()->randomFloat(2, 10000, 100000),
            'subject' => fake()->randomElement([
                'Equipment Purchase',
                'Software License - Annual',
                'Major Renovation',
                'Vehicle Purchase',
                'Infrastructure Upgrade'
            ]),
        ]);
    }

    /**
     * Indicate that the transaction has complete reference numbers.
     */
    public function withReferences(): static
    {
        return $this->state(fn (array $attributes) => [
            'order_number' => fake()->numerify('ORD-####'),
            'check_number' => fake()->numerify('CHK-####'),
            'bank_ref_number' => fake()->numerify('BNK-#######'),
            'note' => 'Transaction with complete reference documentation.',
        ]);
    }

    /**
     * Indicate that the transaction is from this month.
     */
    public function thisMonth(): static
    {
        return $this->state(fn (array $attributes) => [
            'date' => fake()->dateTimeBetween('first day of this month', 'now'),
        ]);
    }
}
