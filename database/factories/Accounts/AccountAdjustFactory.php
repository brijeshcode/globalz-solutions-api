<?php

namespace Database\Factories\Accounts;

use App\Models\Accounts\Account;
use App\Models\Accounts\AccountAdjust;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Accounts\AccountAdjust>
 */
class AccountAdjustFactory extends Factory
{
    protected $model = AccountAdjust::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'date'       => fake()->dateTimeBetween('-1 year', 'now'),
            'type'       => fake()->randomElement(['Credit', 'Debit']),
            'account_id' => Account::factory(),
            'amount'     => fake()->randomFloat(2, 10, 10000),
            'note'       => fake()->optional(0.5)->sentence(),
        ];
    }

    public function credit(): static
    {
        return $this->state(['type' => 'Credit']);
    }

    public function debit(): static
    {
        return $this->state(['type' => 'Debit']);
    }
}
