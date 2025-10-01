<?php

namespace Database\Factories\Customers;

use App\Models\Customers\Customer;
use App\Models\Setups\Generals\Currencies\Currency;
use App\Models\Setups\Warehouse;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Customers\Sale>
 */
class SaleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $subTotal = $this->faker->numberBetween(100, 5000);
        $discountAmount = $this->faker->numberBetween(0, $subTotal * 0.1);
        $total = $subTotal - $discountAmount;
        $currencyRate = $this->faker->randomFloat(4, 0.5, 2.0);

        return [
            'code' => $this->faker->unique()->numerify('######'),
            'date' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'prefix' => $this->faker->randomElement(['INV', 'INX']),
            'salesperson_id' => User::factory(),
            'customer_id' => Customer::factory(),
            'currency_id' => Currency::factory(),
            'warehouse_id' => Warehouse::factory(),
            'customer_payment_term_id' => null,
            'customer_last_payment_receipt_id' => null,
            'client_po_number' => $this->faker->optional()->bothify('PO-####-????'),
            'currency_rate' => $currencyRate,
            'credit_limit' => $this->faker->numberBetween(0, 10000),
            'outStanding_balance' => $this->faker->numberBetween(0, 5000),
            'sub_total' => $subTotal,
            'sub_total_usd' => round($subTotal / $currencyRate, 2),
            'discount_amount' => $discountAmount,
            'discount_amount_usd' => round($discountAmount / $currencyRate, 2),
            'total' => $total,
            'total_usd' => round($total / $currencyRate, 2),
            'note' => $this->faker->optional()->sentence(),
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }

    /**
     * Configure the model factory for INV prefix (tax sales)
     */
    public function taxSale(): static
    {
        return $this->state(fn (array $attributes) => [
            'prefix' => 'INV',
        ]);
    }

    /**
     * Configure the model factory for INX prefix (no tax sales)
     */
    public function noTaxSale(): static
    {
        return $this->state(fn (array $attributes) => [
            'prefix' => 'INX',
        ]);
    }

    /**
     * Configure the model factory with specific warehouse
     */
    public function forWarehouse(int $warehouseId): static
    {
        return $this->state(fn (array $attributes) => [
            'warehouse_id' => $warehouseId,
        ]);
    }

    /**
     * Configure the model factory with specific currency
     */
    public function forCurrency(int $currencyId): static
    {
        return $this->state(fn (array $attributes) => [
            'currency_id' => $currencyId,
        ]);
    }

    /**
     * Configure the model factory for a specific date
     */
    public function onDate(string $date): static
    {
        return $this->state(fn (array $attributes) => [
            'date' => $date,
        ]);
    }

    /**
     * Configure the model factory with minimal data
     */
    public function minimal(): static
    {
        return $this->state(fn (array $attributes) => [
            'sub_total' => 100.00,
            'sub_total_usd' => 80.00,
            'discount_amount' => 0.00,
            'discount_amount_usd' => 0.00,
            'total' => 100.00,
            'total_usd' => 80.00,
            'currency_rate' => 1.25,
        ]);
    }
}
