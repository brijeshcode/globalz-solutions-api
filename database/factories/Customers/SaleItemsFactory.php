<?php

namespace Database\Factories\Customers;

use App\Models\Customers\Sale;
use App\Models\Items\Item;
use App\Models\Setups\Supplier;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Customers\SaleItems>
 */
class SaleItemsFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $price = $this->faker->randomFloat(2, 10, 500);
        $quantity = $this->faker->randomFloat(4, 1, 100);
        $discountPercent = $this->faker->numberBetween(0, 20);
        $unitDiscountAmount = ($price * $discountPercent) / 100;
        $discountAmount = $unitDiscountAmount * $quantity;
        $totalPrice = ($price * $quantity) - $discountAmount;

        return [
            'sale_id' => Sale::factory(),
            'item_code' => $this->faker->bothify('ITEM-####'),
            'supplier_id' => Supplier::factory(),
            'item_id' => Item::factory(),
            'quantity' => $quantity,
            'price' => $price,
            'ttc_price' => $price * 1.1, // Assuming 10% tax
            'discount_percent' => $discountPercent,
            'unit_discount_amount' => $unitDiscountAmount,
            'discount_amount' => $discountAmount,
            'total_price' => $totalPrice,
            'total_price_usd' => $totalPrice * 0.8, // Assuming conversion rate
            'note' => $this->faker->optional()->sentence(),
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }

    /**
     * Configure the model factory for a specific sale
     */
    public function forSale(int $saleId): static
    {
        return $this->state(fn (array $attributes) => [
            'sale_id' => $saleId,
        ]);
    }

    /**
     * Configure the model factory for a specific item
     */
    public function forItem(int $itemId, ?string $itemCode = null): static
    {
        return $this->state(fn (array $attributes) => [
            'item_id' => $itemId,
            'item_code' => $itemCode ?? $this->faker->bothify('ITEM-####'),
        ]);
    }

    /**
     * Configure the model factory with no discount
     */
    public function noDiscount(): static
    {
        return $this->state(function (array $attributes) {
            $price = $attributes['price'] ?? $this->faker->randomFloat(2, 10, 500);
            $quantity = $attributes['quantity'] ?? $this->faker->randomFloat(4, 1, 100);
            $totalPrice = $price * $quantity;

            return [
                'discount_percent' => 0,
                'unit_discount_amount' => 0,
                'discount_amount' => 0,
                'total_price' => $totalPrice,
                'total_price_usd' => $totalPrice * 0.8,
            ];
        });
    }

    /**
     * Configure the model factory with specific price and quantity
     */
    public function withPriceAndQuantity(float $price, float $quantity, float $discountPercent = 0): static
    {
        return $this->state(function (array $attributes) use ($price, $quantity, $discountPercent) {
            $unitDiscountAmount = ($price * $discountPercent) / 100;
            $discountAmount = $unitDiscountAmount * $quantity;
            $totalPrice = ($price * $quantity) - $discountAmount;

            return [
                'price' => $price,
                'quantity' => $quantity,
                'discount_percent' => $discountPercent,
                'unit_discount_amount' => $unitDiscountAmount,
                'discount_amount' => $discountAmount,
                'total_price' => $totalPrice,
                'total_price_usd' => $totalPrice * 0.8,
            ];
        });
    }

    /**
     * Configure the model factory with minimal data
     */
    public function minimal(): static
    {
        return $this->state(fn (array $attributes) => [
            'price' => 100.00,
            'quantity' => 1,
            'discount_percent' => 0,
            'unit_discount_amount' => 0,
            'discount_amount' => 0,
            'total_price' => 100.00,
            'total_price_usd' => 80.00,
        ]);
    }
}
