<?php

namespace Database\Factories\Setups;

use App\Models\Setups\Currency;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Setups\Currency>
 */
class CurrencyFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Currency::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $currencies = [
            ['name' => 'US Dollar', 'code' => 'USD', 'symbol' => '$'],
            ['name' => 'Euro', 'code' => 'EUR', 'symbol' => '€'],
            ['name' => 'British Pound', 'code' => 'GBP', 'symbol' => '£'],
            ['name' => 'Japanese Yen', 'code' => 'JPY', 'symbol' => '¥'],
            ['name' => 'Canadian Dollar', 'code' => 'CAD', 'symbol' => 'C$'],
            ['name' => 'Australian Dollar', 'code' => 'AUD', 'symbol' => 'A$'],
            ['name' => 'Swiss Franc', 'code' => 'CHF', 'symbol' => 'CHF'],
            ['name' => 'Chinese Yuan', 'code' => 'CNY', 'symbol' => '¥'],
            ['name' => 'Indian Rupee', 'code' => 'INR', 'symbol' => '₹'],
            ['name' => 'Mexican Peso', 'code' => 'MXN', 'symbol' => '$'],
        ];

        $currency = fake()->randomElement($currencies);

        return [
            'name' => fake()->unique()->currencyCode() . ' Currency',
            'code' => fake()->unique()->currencyCode(),
            'symbol' => fake()->randomElement(['$', '€', '£', '¥', '₹', '₹', 'C$', 'A$', 'CHF']),
            'symbol_position' => fake()->randomElement(['before', 'after']),
            'decimal_places' => fake()->numberBetween(0, 4),
            'decimal_separator' => fake()->randomElement(['.', ',']),
            'thousand_separator' => fake()->randomElement([',', '.', ' ', '']),
            'is_active' => fake()->boolean(85), // 85% chance of being active
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }

    /**
     * Indicate that the currency is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the currency is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Create USD currency.
     */
    public function usd(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'US Dollar',
            'code' => 'USD',
            'symbol' => '$',
            'symbol_position' => 'before',
            'decimal_places' => 2,
            'decimal_separator' => '.',
            'thousand_separator' => ',',
        ]);
    }

    /**
     * Create EUR currency.
     */
    public function eur(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Euro',
            'code' => 'EUR',
            'symbol' => '€',
            'symbol_position' => 'before',
            'decimal_places' => 2,
            'decimal_separator' => ',',
            'thousand_separator' => '.',
        ]);
    }

    /**
     * Create GBP currency.
     */
    public function gbp(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'British Pound',
            'code' => 'GBP',
            'symbol' => '£',
            'symbol_position' => 'before',
            'decimal_places' => 2,
            'decimal_separator' => '.',
            'thousand_separator' => ',',
        ]);
    }

    /**
     * Create JPY currency (no decimal places).
     */
    public function jpy(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Japanese Yen',
            'code' => 'JPY',
            'symbol' => '¥',
            'symbol_position' => 'before',
            'decimal_places' => 0,
            'decimal_separator' => '.',
            'thousand_separator' => ',',
        ]);
    }

    /**
     * Create INR currency.
     */
    public function inr(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Indian Rupee',
            'code' => 'INR',
            'symbol' => '₹',
            'symbol_position' => 'before',
            'decimal_places' => 2,
            'decimal_separator' => '.',
            'thousand_separator' => ',',
        ]);
    }

    /**
     * Set specific symbol position.
     */
    public function symbolPosition(string $position): static
    {
        return $this->state(fn (array $attributes) => [
            'symbol_position' => $position,
        ]);
    }

    /**
     * Set specific decimal places.
     */
    public function decimalPlaces(int $places): static
    {
        return $this->state(fn (array $attributes) => [
            'decimal_places' => $places,
        ]);
    }

    /**
     * Use existing users for created_by and updated_by.
     */
    public function withExistingUsers(): static
    {
        return $this->state(function (array $attributes) {
            $userIds = User::pluck('id')->toArray();
            
            if (empty($userIds)) {
                return $attributes;
            }

            return [
                'created_by' => fake()->randomElement($userIds),
                'updated_by' => fake()->randomElement($userIds),
            ];
        });
    }

    /**
     * Create a custom currency with unique code.
     */
    public function custom(string $name, string $code, ?string $symbol = null): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $name,
            'code' => strtoupper($code),
            'symbol' => $symbol ?? $code,
        ]);
    }
}