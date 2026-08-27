<?php

namespace Database\Factories\Items;

use App\Models\Items\Item;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Items\ItemOffer>
 */
class ItemOfferFactory extends Factory
{
    public function definition(): array
    {
        $date = fake()->dateTimeBetween('-1 month', 'now')->format('Y-m-d');

        return [
            'item_id'             => Item::factory(),
            'free_item_id'        => Item::factory(),
            'date'                => $date,
            'validity_date'       => fake()->dateTimeBetween('+1 month', '+6 months')->format('Y-m-d'),
            'minimum_quantity'    => fake()->numberBetween(1, 10),
            'free_quantity'       => fake()->numberBetween(1, 5),
            'usage_limit'         => fake()->numberBetween(10, 200),
            'can_change_quantity' => false,
            'allow_multiple'      => false,
            'used_count'          => 0,
            'is_active'           => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }

    public function expired(): static
    {
        return $this->state([
            'date'          => '2025-01-01',
            'validity_date' => '2025-06-01',
        ]);
    }

    public function limitReached(): static
    {
        return $this->state(fn (array $attributes) => [
            'used_count'  => $attributes['usage_limit'],
        ]);
    }
}
