<?php

namespace Database\Factories\Inventory;

use App\Models\Items\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Inventory\ItemPrice>
 */
class ItemPriceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'item_id' => Item::factory()->create()->id,
            'price_usd' => 100,
            'last_purchase_id' => 1,
            'effective_date' => now()
        ];
    }
}
