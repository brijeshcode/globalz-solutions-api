<?php

namespace Database\Factories\Suppliers;

use App\Models\Items\Item;
use App\Models\Suppliers\Purchase;
use App\Models\Suppliers\PurchaseItem;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Suppliers\PurchaseItem>
 */
class PurchaseItemFactory extends Factory
{
    protected $model = PurchaseItem::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $price = $this->faker->randomFloat(2, 5, 1000);
        $quantity = $this->faker->randomFloat(4, 0.1, 100);
        
        // Randomly choose between percentage or fixed discount
        $usePercentageDiscount = $this->faker->boolean();
        $discountPercent = $usePercentageDiscount ? $this->faker->randomFloat(2, 0, 25) : 0;
        $discountAmount = $usePercentageDiscount ? 0 : $this->faker->randomFloat(2, 0, $price * $quantity * 0.2);
        
        // Calculate total price after discount
        $grossTotal = $price * $quantity;
        $totalDiscount = $usePercentageDiscount ? 
            ($grossTotal * $discountPercent / 100) : 
            $discountAmount;
        $totalPrice = $grossTotal - $totalDiscount;
        
        // USD amounts (assuming conversion already applied)
        $totalPriceUsd = $totalPrice;
        $shippingPerItem = $this->faker->randomFloat(2, 0, 50);
        $customsPerItem = $this->faker->randomFloat(2, 0, 30);
        $otherPerItem = $this->faker->randomFloat(2, 0, 10);
        
        $finalTotalCostUsd = $totalPriceUsd + $shippingPerItem + $customsPerItem + $otherPerItem;
        $costPerItemUsd = $finalTotalCostUsd / $quantity;

        return [
            'item_code' => function (array $attributes) {
                $item = Item::find($attributes['item_id'] ?? Item::factory()->create()->id);
                return $item->code;
            },
            'purchase_id' => Purchase::factory(),
            'item_id' => Item::factory(),
            'price' => $price,
            'quantity' => $quantity,
            'discount_percent' => $discountPercent,
            'discount_amount' => $discountAmount,
            'total_price' => $totalPrice,
            'total_price_usd' => $totalPriceUsd,
            'total_shipping_usd' => $shippingPerItem,
            'total_customs_usd' => $customsPerItem,
            'total_other_usd' => $otherPerItem,
            'final_total_cost_usd' => $finalTotalCostUsd,
            'cost_per_item_usd' => $costPerItemUsd,
            'note' => $this->faker->optional(0.3)->sentence(),
            'created_by' => User::factory(),
            'updated_by' => function (array $attributes) {
                return $this->faker->boolean(20) ? User::factory() : $attributes['created_by'];
            },
        ];
    }

    /**
     * Purchase item with percentage discount
     */
    public function withPercentageDiscount(float $percent = null): static
    {
        $discountPercent = $percent ?? $this->faker->randomFloat(2, 5, 30);
        
        return $this->state(function (array $attributes) use ($discountPercent) {
            $grossTotal = $attributes['price'] * $attributes['quantity'];
            $discountAmount = $grossTotal * $discountPercent / 100;
            $totalPrice = $grossTotal - $discountAmount;
            
            return [
                'discount_percent' => $discountPercent,
                'discount_amount' => 0,
                'total_price' => $totalPrice,
                'total_price_usd' => $totalPrice,
            ];
        });
    }

    /**
     * Purchase item with fixed discount amount
     */
    public function withFixedDiscount(float $amount = null): static
    {
        return $this->state(function (array $attributes) use ($amount) {
            $discountAmount = $amount ?? $this->faker->randomFloat(2, 10, 100);
            $grossTotal = $attributes['price'] * $attributes['quantity'];
            $totalPrice = max(0, $grossTotal - $discountAmount);
            
            return [
                'discount_percent' => 0,
                'discount_amount' => $discountAmount,
                'total_price' => $totalPrice,
                'total_price_usd' => $totalPrice,
            ];
        });
    }

    /**
     * Purchase item without any discount
     */
    public function withoutDiscount(): static
    {
        return $this->state(function (array $attributes) {
            $totalPrice = $attributes['price'] * $attributes['quantity'];
            
            return [
                'discount_percent' => 0,
                'discount_amount' => 0,
                'total_price' => $totalPrice,
                'total_price_usd' => $totalPrice,
            ];
        });
    }

    /**
     * High-value purchase item
     */
    public function highValue(): static
    {
        return $this->state(function (array $attributes) {
            $price = $this->faker->randomFloat(2, 1000, 10000);
            $quantity = $this->faker->randomFloat(4, 1, 50);
            $totalPrice = $price * $quantity;
            
            return [
                'price' => $price,
                'quantity' => $quantity,
                'total_price' => $totalPrice,
                'total_price_usd' => $totalPrice,
                'final_total_cost_usd' => $totalPrice + $this->faker->randomFloat(2, 100, 1000),
            ];
        });
    }

    /**
     * Small quantity purchase item
     */
    public function smallQuantity(): static
    {
        return $this->state(fn (array $attributes) => [
            'quantity' => $this->faker->randomFloat(4, 0.01, 5),
        ]);
    }

    /**
     * Bulk purchase item
     */
    public function bulk(): static
    {
        return $this->state(function (array $attributes) {
            $quantity = $this->faker->randomFloat(4, 100, 1000);
            $totalPrice = $attributes['price'] * $quantity;
            
            return [
                'quantity' => $quantity,
                'total_price' => $totalPrice,
                'total_price_usd' => $totalPrice,
            ];
        });
    }

    /**
     * Purchase item with high shipping costs
     */
    public function withHighShipping(): static
    {
        return $this->state(function (array $attributes) {
            $shippingCost = $this->faker->randomFloat(2, 100, 500);
            $finalTotal = $attributes['total_price_usd'] + $shippingCost;
            
            return [
                'total_shipping_usd' => $shippingCost,
                'final_total_cost_usd' => $finalTotal,
                'cost_per_item_usd' => $finalTotal / $attributes['quantity'],
            ];
        });
    }

    /**
     * Purchase item for specific item and purchase
     */
    public function forPurchaseAndItem(Purchase $purchase, Item $item): static
    {
        return $this->state(fn (array $attributes) => [
            'purchase_id' => $purchase->id,
            'item_id' => $item->id,
            'item_code' => $item->code,
        ]);
    }

    /**
     * Purchase item with realistic pricing
     */
    public function realistic(): static
    {
        return $this->state(function (array $attributes) {
            // More realistic price ranges based on item types
            $basePrice = $this->faker->randomElement([
                $this->faker->randomFloat(2, 1, 50),      // Small items
                $this->faker->randomFloat(2, 50, 200),    // Medium items  
                $this->faker->randomFloat(2, 200, 1000),  // Large items
                $this->faker->randomFloat(2, 1000, 5000), // Equipment
            ]);
            
            $quantity = $this->faker->randomElement([
                $this->faker->randomFloat(2, 1, 10),      // Small quantities
                $this->faker->randomFloat(2, 10, 100),    // Medium quantities
                $this->faker->randomFloat(2, 100, 1000),  // Bulk quantities
            ]);
            
            $totalPrice = $basePrice * $quantity;
            
            return [
                'price' => $basePrice,
                'quantity' => $quantity,
                'total_price' => $totalPrice,
                'total_price_usd' => $totalPrice,
            ];
        });
    }
}
