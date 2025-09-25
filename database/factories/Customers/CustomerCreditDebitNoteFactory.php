<?php

namespace Database\Factories\Customers;

use App\Models\Customers\Customer;
use App\Models\Setups\Generals\Currencies\Currency;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Customers\CustomerCreditDebitNote>
 */
class CustomerCreditDebitNoteFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $type = $this->faker->randomElement(['credit', 'debit']);
        $amount = $this->faker->randomFloat(2, 50, 5000);
        $currencyRate = $this->faker->randomFloat(4, 0.5, 2.0);

        $prefixes = [
            'credit' => ['CRX', 'CRN'],
            'debit' => ['DBX', 'DBN']
        ];

        return [
            // Don't set code - let the model auto-generate it
            'date' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'prefix' => $this->faker->randomElement($prefixes[$type]),
            'type' => $type,
            'customer_id' => null, // Will be set explicitly in tests
            'currency_id' => null, // Will be set explicitly in tests
            'currency_rate' => $currencyRate,
            'amount' => $amount,
            'amount_usd' => round($amount / $currencyRate, 2),
            'note' => $this->faker->optional(0.7)->sentence(),
        ];
    }

    /**
     * Create a credit note
     */
    public function credit(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'type' => 'credit',
                'prefix' => $this->faker->randomElement(['CRX', 'CRN']),
            ];
        });
    }

    /**
     * Create a debit note
     */
    public function debit(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'type' => 'debit',
                'prefix' => $this->faker->randomElement(['DBX', 'DBN']),
            ];
        });
    }

    /**
     * Create note with specific prefix
     */
    public function withPrefix(string $prefix): static
    {
        return $this->state(function (array $attributes) use ($prefix) {
            $type = in_array($prefix, ['CRX', 'CRN']) ? 'credit' : 'debit';

            return [
                'prefix' => $prefix,
                'type' => $type,
            ];
        });
    }

    /**
     * Create note with specific amount
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
     * Create note for specific customer
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
     * Create note with specific currency
     */
    public function withCurrency(Currency $currency, float $rate = null): static
    {
        return $this->state(function (array $attributes) use ($currency, $rate) {
            $currencyRate = $rate ?? $this->faker->randomFloat(4, 0.5, 2.0);
            $amount = $attributes['amount'] ?? $this->faker->randomFloat(2, 50, 5000);

            return [
                'currency_id' => $currency->id,
                'currency_rate' => $currencyRate,
                'amount_usd' => round($amount / $currencyRate, 2),
            ];
        });
    }

    /**
     * Create note with detailed note
     */
    public function withNote(string $note = null): static
    {
        return $this->state(function (array $attributes) use ($note) {
            return [
                'note' => $note ?? $this->faker->paragraph(),
            ];
        });
    }

    /**
     * Create recent note (last 30 days)
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
     * Create old note (older than 1 year)
     */
    public function old(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'date' => $this->faker->dateTimeBetween('-3 years', '-1 year'),
            ];
        });
    }

    /**
     * Create large amount note
     */
    public function largeAmount(): static
    {
        return $this->state(function (array $attributes) {
            $amount = $this->faker->randomFloat(2, 5000, 50000);
            $currencyRate = $attributes['currency_rate'] ?? 1.0;

            return [
                'amount' => $amount,
                'amount_usd' => round($amount / $currencyRate, 2),
            ];
        });
    }

    /**
     * Create small amount note
     */
    public function smallAmount(): static
    {
        return $this->state(function (array $attributes) {
            $amount = $this->faker->randomFloat(2, 1, 100);
            $currencyRate = $attributes['currency_rate'] ?? 1.0;

            return [
                'amount' => $amount,
                'amount_usd' => round($amount / $currencyRate, 2),
            ];
        });
    }
}
