<?php

namespace Database\Factories\Employees;

use App\Models\Employees\Employee;
use App\Models\Setups\Generals\Currencies\Currency;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Employees\EmployeeCreditDebitNote>
 */
class EmployeeCreditDebitNoteFactory extends Factory
{
    public function definition(): array
    {
        $type = $this->faker->randomElement(['credit', 'debit']);
        $amount = $this->faker->randomFloat(2, 50, 5000);
        $currencyRate = $this->faker->randomFloat(4, 0.5, 2.0);

        $prefixes = [
            'credit' => ['ECRX', 'ECRN'],
            'debit'  => ['EDBX', 'EDBN'],
        ];

        return [
            'date'          => $this->faker->dateTimeBetween('-1 year', 'now'),
            'prefix'        => $this->faker->randomElement($prefixes[$type]),
            'type'          => $type,
            'employee_id'   => null,
            'currency_id'   => null,
            'currency_rate' => $currencyRate,
            'amount'        => $amount,
            'amount_usd'    => round($amount * $currencyRate, 2),
            'note'          => $this->faker->optional(0.7)->sentence(),
        ];
    }

    public function credit(): static
    {
        return $this->state(fn () => [
            'type'   => 'credit',
            'prefix' => $this->faker->randomElement(['ECRX', 'ECRN']),
        ]);
    }

    public function debit(): static
    {
        return $this->state(fn () => [
            'type'   => 'debit',
            'prefix' => $this->faker->randomElement(['EDBX', 'EDBN']),
        ]);
    }

    public function withPrefix(string $prefix): static
    {
        return $this->state(fn () => [
            'prefix' => $prefix,
            'type'   => in_array($prefix, ['ECRX', 'ECRN']) ? 'credit' : 'debit',
        ]);
    }

    public function forEmployee(Employee $employee): static
    {
        return $this->state(fn () => ['employee_id' => $employee->id]);
    }

    public function withCurrency(Currency $currency, float $rate = null): static
    {
        return $this->state(function (array $attributes) use ($currency, $rate) {
            $currencyRate = $rate ?? $this->faker->randomFloat(4, 0.5, 2.0);
            $amount = $attributes['amount'] ?? $this->faker->randomFloat(2, 50, 5000);

            return [
                'currency_id'   => $currency->id,
                'currency_rate' => $currencyRate,
                'amount_usd'    => round($amount * $currencyRate, 2),
            ];
        });
    }

    public function withAmount(float $amount, float $currencyRate = 1.0): static
    {
        return $this->state(fn () => [
            'amount'        => $amount,
            'amount_usd'    => round($amount * $currencyRate, 2),
            'currency_rate' => $currencyRate,
        ]);
    }

    public function recent(): static
    {
        return $this->state(fn () => [
            'date' => $this->faker->dateTimeBetween('-30 days', 'now'),
        ]);
    }

    public function old(): static
    {
        return $this->state(fn () => [
            'date' => $this->faker->dateTimeBetween('-3 years', '-1 year'),
        ]);
    }
}
