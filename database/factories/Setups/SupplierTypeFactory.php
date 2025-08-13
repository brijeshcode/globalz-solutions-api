<?php

namespace Database\Factories\Setups;

use App\Models\Setups\SupplierType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Setups\SupplierType>
 */
class SupplierTypeFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = SupplierType::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $supplierTypes = [
            'Manufacturer',
            'Distributor',
            'Wholesaler',
            'Retailer',
            'Service Provider',
            'Raw Material Supplier',
            'Equipment Supplier',
            'Technology Vendor',
            'Logistics Provider',
            'Consulting Firm',
            'Construction Contractor',
            'Maintenance Provider',
            'Software Vendor',
            'Food Supplier',
            'Medical Supplier',
            'Office Supplies',
            'Marketing Agency',
            'Legal Services',
            'Financial Services',
            'Transportation Company'
        ];

        $descriptions = [
            'Primary supplier of goods and services',
            'Authorized distributor for multiple brands',
            'Bulk supplier with competitive pricing',
            'Direct retail partner for consumer goods',
            'Professional services and consultation',
            'Raw materials and components supplier',
            'Industrial equipment and machinery',
            'Technology solutions and software',
            'Shipping and logistics coordination',
            'Expert advisory and consulting services',
        ];

        return [
            'name' => $this->faker->unique()->randomElement($supplierTypes),
            'description' => $this->faker->optional(0.8)->randomElement($descriptions),
            'is_active' => $this->faker->boolean(85), // 85% chance of being active
            'created_by' => User::factory(),
            'updated_by' => null,
        ];
    }

    /**
     * Indicate that the supplier type is active.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => true,
        ]);
    }

    /**
     * Indicate that the supplier type is inactive.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }

    /**
     * Indicate that the supplier type has no description.
     */
    public function withoutDescription(): static
    {
        return $this->state(fn (array $attributes) => [
            'description' => null,
        ]);
    }

    /**
     * Set a specific name for the supplier type.
     */
    public function withName(string $name): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => $name,
        ]);
    }

    /**
     * Set created_by and updated_by to the same user.
     */
    public function updatedBy(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);
    }
}