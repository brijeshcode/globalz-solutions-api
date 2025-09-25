<?php

namespace Database\Factories\Customers;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Customers\CustomerReturn>
 */
class CustomerReturnFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => $this->faker->unique()->numerify('######'),
            'date' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'prefix' => 'RTV',
            'salesperson_id' => \App\Models\User::factory(),
            'customer_id' => \App\Models\Customers\Customer::factory()->state(fn () => [
                'salesperson_id' => \App\Models\Employees\Employee::factory(),
            ]),
            'currency_id' => \App\Models\Setups\Generals\Currencies\Currency::factory(),
            'warehouse_id' => \App\Models\Setups\Warehouse::factory(),
            'total' => $this->faker->randomFloat(2, 100, 5000),
            'total_usd' => $this->faker->randomFloat(2, 100, 5000),
            'total_volume_cbm' => $this->faker->randomFloat(4, 0.1, 10),
            'total_weight_kg' => $this->faker->randomFloat(4, 1, 100),
            'note' => $this->faker->optional()->sentence(),
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'approved_by' => \App\Models\User::factory(),
            'approved_at' => $this->faker->dateTimeBetween('-1 month', 'now'),
            'approve_note' => $this->faker->optional()->sentence(),
        ]);
    }

    public function received(): static
    {
        return $this->state(fn (array $attributes) => [
            'return_received_by' => \App\Models\User::factory(),
            'return_received_at' => $this->faker->dateTimeBetween('-1 week', 'now'),
            'return_received_note' => $this->faker->optional()->sentence(),
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'approved_by' => null,
            'approved_at' => null,
            'approve_note' => null,
        ]);
    }

    public function withItems(int $count = 3): static
    {
        return $this->has(
            \App\Models\Customers\CustomerReturnItem::factory()->count($count),
            'items'
        );
    }
}
