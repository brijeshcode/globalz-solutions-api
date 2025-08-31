<?php

namespace Database\Factories\Setups\Expenses;

use App\Models\Setups\Expenses\ExpenseCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Setups\Expenses\ExpenseCategory>
 */
class ExpenseCategoryFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->unique()->randomElement([
                'Office Supplies',
                'Travel & Transportation',
                'Marketing & Advertising',
                'Professional Services',
                'Utilities',
                'Insurance',
                'Training & Development',
                'Equipment & Software',
                'Maintenance & Repairs',
                'Communication',
                'Entertainment & Events',
                'Legal & Compliance',
                'Rent & Property',
                'Banking & Finance',
                'Research & Development',
            ]),
            'description' => $this->faker->optional(0.7)->sentence(10),
            'is_active' => $this->faker->boolean(85),
            'parent_id' => null, // Will be set explicitly when creating hierarchies
        ];
    }

    /**
     * Create a subcategory with a parent
     */
    public function subcategory(ExpenseCategory $parent): static
    {
        return $this->state(function (array $attributes) use ($parent) {
            return [
                'parent_id' => $parent->id,
                'name' => $this->faker->unique()->randomElement([
                    'Office Stationery',
                    'Computer Hardware',
                    'Software Licenses',
                    'Business Travel',
                    'Local Transportation',
                    'Vehicle Expenses',
                    'Internet Services',
                    'Phone Bills',
                    'Electricity',
                    'Water & Gas',
                    'Training Materials',
                    'Conference Fees',
                    'Legal Consultation',
                    'Accounting Services',
                ]),
            ];
        });
    }

    /**
     * Create an inactive category
     */
    public function inactive(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'is_active' => false,
            ];
        });
    }

    /**
     * Create a category without description
     */
    public function withoutDescription(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'description' => null,
            ];
        });
    }
}
