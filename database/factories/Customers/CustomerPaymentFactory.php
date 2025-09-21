<?php

namespace Database\Factories\Customers;

use App\Models\Accounts\Account;
use App\Models\Customers\Customer;
use App\Models\Setups\Customers\CustomerPaymentTerm;
use App\Models\Setups\Generals\Currencies\Currency;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Customers\CustomerPayment>
 */
class CustomerPaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = $this->faker->randomFloat(2, 100, 10000);
        $currencyRate = $this->faker->randomFloat(4, 0.5, 2.0);

        return [
            'date' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'prefix' => $this->faker->randomElement(['RCT', 'RCX']),
            'customer_id' => null, // Will be set explicitly in tests
            'customer_payment_term_id' => null, // Will be set explicitly in tests
            'currency_id' => null, // Will be set explicitly in tests
            'currency_rate' => $currencyRate,
            'amount' => $amount,
            'amount_usd' => round($amount / $currencyRate, 2),
            'credit_limit' => $this->faker->randomFloat(2, 0, 50000),
            'last_payment_amount' => $this->faker->randomFloat(2, 0, 5000),
            'rtc_book_number' => 'RTC-' . $this->faker->unique()->numberBetween(100000, 999999),
            'note' => $this->faker->optional()->sentence(),
        ];
    }

    /**
     * Create an approved payment
     */
    public function approved(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'approved_by' => null, // Will be set explicitly in tests
                'approved_at' => $this->faker->dateTimeBetween('-6 months', 'now'),
                'account_id' => null, // Will be set explicitly in tests
                'approve_note' => $this->faker->optional()->sentence(),
            ];
        });
    }

    /**
     * Create a pending payment (default state)
     */
    public function pending(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'approved_by' => null,
                'approved_at' => null,
                'account_id' => null,
                'approve_note' => null,
            ];
        });
    }

    /**
     * Create payment with specific prefix
     */
    public function withPrefix(string $prefix): static
    {
        return $this->state(function (array $attributes) use ($prefix) {
            return [
                'prefix' => $prefix,
            ];
        });
    }

    /**
     * Create payment with specific amount
     */
    public function withAmount(float $amount, float $currencyRate = 1.0): static
    {
        return $this->state(function (array $attributes) use ($amount, $currencyRate) {
            return [
                'amount' => $amount,
                'amount_usd' => round($amount / $currencyRate, 2),
                'currency_rate' => $currencyRate,
            ];
        });
    }

    /**
     * Create payment for specific customer
     */
    public function forCustomer(Customer $customer): static
    {
        return $this->state(function (array $attributes) use ($customer) {
            return [
                'customer_id' => $customer->id,
            ];
        });
    }

    /**
     * Create payment with specific currency
     */
    public function withCurrency(Currency $currency, float $rate = null): static
    {
        return $this->state(function (array $attributes) use ($currency, $rate) {
            $currencyRate = $rate ?? $this->faker->randomFloat(4, 0.5, 2.0);
            $amount = $attributes['amount'] ?? $this->faker->randomFloat(2, 100, 10000);

            return [
                'currency_id' => $currency->id,
                'currency_rate' => $currencyRate,
                'amount_usd' => round($amount / $currencyRate, 2),
            ];
        });
    }

    /**
     * Create recent payment (last 30 days)
     */
    public function recent(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'date' => $this->faker->dateTimeBetween('-30 days', 'now'),
            ];
        });
    }

    /**
     * Create old payment (older than 1 year)
     */
    public function old(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'date' => $this->faker->dateTimeBetween('-3 years', '-1 year'),
            ];
        });
    }
}
