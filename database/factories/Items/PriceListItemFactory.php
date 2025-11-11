<?php

namespace Database\Factories\Items;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Items\PriceListItem>
 */
class PriceListItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'price_list_id' => \App\Models\Items\PriceList::factory(),
            'item_code' => fake()->bothify('ITEM-####'),
            'item_id' => \App\Models\Items\Item::factory(),
            'item_description' => fake()->sentence(4),
            'sell_price' => fake()->randomFloat(2, 10, 1000),
            'created_by' => \App\Models\User::factory(),
            'updated_by' => function (array $attributes) {
                return $attributes['created_by'];
            },
        ];
    }
}
