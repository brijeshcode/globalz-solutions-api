<?php

namespace Database\Factories\Items;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Items\PriceList>
 */
class PriceListFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('PL-####'),
            'description' => fake()->sentence(3),
            'item_count' => 0,
            'note' => fake()->optional()->sentence(),
            'created_by' => \App\Models\User::factory(),
            'updated_by' => function (array $attributes) {
                return $attributes['created_by'];
            },
        ];
    }
}
