<?php

namespace Database\Factories\Setups;

use App\Models\Setups\Warehouse;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Setups\Warehouse>
 */
class WarehouseFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Warehouse::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company() . ' Warehouse',
            'note' => fake()->optional(0.7)->paragraph(),
            'is_active' => fake()->boolean(85), // 85% chance of being active
            'address_line_1' => fake()->streetAddress(),
            'address_line_2' => fake()->optional(0.3)->secondaryAddress(),
            'city' => fake()->city(),
            'state' => fake()->state(),
            'postal_code' => fake()->postcode(),
            'country' => fake()->country(),
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
        ];
    }

    /**
     * Indicate that the warehouse is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the warehouse is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the warehouse has no note.
     */
    public function withoutNote(): static
    {
        return $this->state(fn (array $attributes) => [
            'note' => null,
        ]);
    }

    /**
     * Indicate that the warehouse has no second address line.
     */
    public function withoutAddressLine2(): static
    {
        return $this->state(fn (array $attributes) => [
            'address_line_2' => null,
        ]);
    }

    /**
     * Set specific country for the warehouse.
     */
    public function inCountry(string $country): static
    {
        return $this->state(fn (array $attributes) => [
            'country' => $country,
        ]);
    }

    /**
     * Set specific city and state for the warehouse.
     */
    public function inLocation(string $city, string $state): static
    {
        return $this->state(fn (array $attributes) => [
            'city' => $city,
            'state' => $state,
        ]);
    }

    /**
     * Use existing users for created_by and updated_by.
     */
    public function withExistingUsers(): static
    {
        return $this->state(function (array $attributes) {
            $userIds = User::pluck('id')->toArray();
            
            if (empty($userIds)) {
                return $attributes;
            }

            return [
                'created_by' => fake()->randomElement($userIds),
                'updated_by' => fake()->randomElement($userIds),
            ];
        });
    }
}