<?php

namespace Database\Factories\Suppliers;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Suppliers\SupplierItemPrice>
 */
class SupplierItemPriceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'supplier_id' => 1,
            'item_id' => 1, 
            'currency_id' => 1,
            'price' => $this->faker->randomFloat(4, 1, 1000),
            'price_usd' => $this->faker->randomFloat(4, 1, 1000),
            'currency_rate' => $this->faker->randomFloat(6, 0.5, 2),
            'last_purchase_date' => $this->faker->date(),
            'is_current' => true,
        ];
    }
}
