<?php

namespace Database\Factories\Setups;

use App\Models\Setups\ItemUnit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class ItemUnitFactory extends Factory
{
    protected $model = ItemUnit::class;

    public function definition(): array
    {
        $unitTypes = ['Pieces', 'Kilograms', 'Grams', 'Boxes', 'Bags', 'Bottles', 'Cartons', 'Dozens', 'Liters', 'Meters', 'Tons', 'Pounds', 'Ounces', 'Yards', 'Feet', 'Inches'];
        $shortNames = ['Pcs', 'Kg', 'g', 'Box', 'Bag', 'Btl', 'Ctn', 'Dz', 'L', 'm', 'T', 'Lb', 'Oz', 'Yd', 'Ft', 'In'];

        return [
            'name' => $this->faker->unique()->randomElement($unitTypes) . ' ' . $this->faker->unique()->numberBetween(1, 9999),
            'short_name' => $this->faker->randomElement($shortNames),
            'description' => $this->faker->optional()->sentence(),
            'is_active' => $this->faker->boolean(90),
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }

    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}