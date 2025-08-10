<?php

namespace Database\Factories\Setups;

use App\Models\Setups\ItemProfitMargin;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Setups\ItemProfitMargin>
 */
class ItemProfitMarginFactory extends Factory
{
    protected $model = ItemProfitMargin::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->words(2, true) . ' Margin',
            'margin_percentage' => $this->faker->randomFloat(2, 0, 100),
            'description' => $this->faker->optional()->sentence(),
            'is_active' => $this->faker->boolean(80), // 80% chance of being active
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }

    /**
     * Indicate that the profit margin is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the profit margin is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Set a specific margin percentage.
     */
    public function withMargin(float $percentage): static
    {
        return $this->state(fn (array $attributes) => [
            'margin_percentage' => $percentage,
        ]);
    }

    /**
     * Create common profit margin presets.
     */
    public function lowMargin(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Low Margin',
            'margin_percentage' => $this->faker->randomFloat(2, 5, 15),
        ]);
    }

    public function standardMargin(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Standard Margin',
            'margin_percentage' => $this->faker->randomFloat(2, 15, 30),
        ]);
    }

    public function premiumMargin(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'Premium Margin',
            'margin_percentage' => $this->faker->randomFloat(2, 30, 60),
        ]);
    }
}