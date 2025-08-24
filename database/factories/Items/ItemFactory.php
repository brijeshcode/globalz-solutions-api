<?php

namespace Database\Factories\Items;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Items\Item>
 */
class ItemFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->numerify('####'),
            'short_name' => fake()->words(2, true),
            'description' => fake()->sentence(),
            'item_type_id' => \App\Models\Setups\ItemType::factory(),
            'item_family_id' => \App\Models\Setups\ItemFamily::factory(),
            'item_unit_id' => \App\Models\Setups\ItemUnit::factory(),
            'tax_code_id' => \App\Models\Setups\TaxCode::factory(),
            'base_cost' => fake()->randomFloat(2, 10, 1000),
            'base_sell' => fake()->randomFloat(2, 15, 1200),
            'starting_price' => fake()->randomFloat(2, 12, 1100),
            'starting_quantity' => fake()->randomFloat(2, 0, 100),
            'low_quantity_alert' => fake()->randomFloat(2, 1, 10),
            'cost_calculation' => fake()->randomElement(['weighted_average', 'last_cost']),
            'is_active' => fake()->boolean(80), // 80% chance of being active
            'created_by' => 1,
            'updated_by' => 1,
        ];
    }
}
