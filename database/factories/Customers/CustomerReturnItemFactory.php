<?php

namespace Database\Factories\Customers;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Customers\CustomerReturnItem>
 */
class CustomerReturnItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $quantity = $this->faker->randomFloat(3, 1, 100);
        $price = $this->faker->randomFloat(2, 10, 500);
        $discountPercent = $this->faker->optional(0.3)->randomFloat(2, 0, 20);
        $taxPercent = $this->faker->randomFloat(2, 0, 25);

        return [
            'item_code' => $this->faker->bothify('ITEM-####'),
            'customer_return_id' => \App\Models\Customers\CustomerReturn::factory(),
            'item_id' => \App\Models\Items\Item::factory(),
            'quantity' => $quantity,
            'price' => $price,
            'discount_percent' => $discountPercent ?? 0,
            'unit_discount_amount' => $discountPercent ? 0 : $this->faker->optional(0.2)->randomFloat(2, 1, 50),
            'tax_percent' => $taxPercent,
            'total_volume_cbm' => $this->faker->randomFloat(4, 0.01, 1),
            'total_weight_kg' => $this->faker->randomFloat(4, 0.1, 10),
            'note' => $this->faker->optional()->sentence(),
        ];
    }

    public function withPercentDiscount(float $percent = null): static
    {
        $discountPercent = $percent ?? $this->faker->randomFloat(2, 5, 25);

        return $this->state(fn (array $attributes) => [
            'discount_percent' => $discountPercent,
            'unit_discount_amount' => 0,
        ]);
    }

    public function withUnitDiscount(float $amount = null): static
    {
        $unitDiscount = $amount ?? $this->faker->randomFloat(2, 1, 20);

        return $this->state(fn (array $attributes) => [
            'discount_percent' => 0,
            'unit_discount_amount' => $unitDiscount,
        ]);
    }

    public function noDiscount(): static
    {
        return $this->state(fn (array $attributes) => [
            'discount_percent' => 0,
            'unit_discount_amount' => 0,
        ]);
    }

    public function highQuantity(): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity' => $this->faker->randomFloat(3, 50, 200),
        ]);
    }

    public function lowQuantity(): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity' => $this->faker->randomFloat(3, 1, 10),
        ]);
    }
}
