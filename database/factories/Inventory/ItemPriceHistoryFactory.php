<?php

namespace Database\Factories\Inventory;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Inventory\ItemPriceHistory>
 */
class ItemPriceHistoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'item_id' => \App\Models\Items\Item::factory(),
            'price_usd' => $this->faker->randomFloat(4, 1, 1000),
            'average_waited_price' => $this->faker->randomFloat(4, 1, 1000),
            'latest_price' => $this->faker->randomFloat(4, 1, 1000),
            'effective_date' => $this->faker->date(),
            'source_type' => $this->faker->randomElement(['purchase', 'adjustment', 'manual', 'initial', 'stock_adjustment']),
            'source_id' => $this->faker->optional()->randomNumber(),
            'note' => $this->faker->optional()->sentence(),
        ];
    }
}
