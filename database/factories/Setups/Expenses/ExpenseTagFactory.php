<?php

namespace Database\Factories\Setups\Expenses;

use App\Models\Setups\Expenses\ExpenseTag;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Setups\Expenses\ExpenseTag>
 */
class ExpenseTagFactory extends Factory
{
    public function definition(): array
    {
        $name = $this->faker->unique()->words(2, true);

        return [
            'name'        => ucwords($name),
            'code'        => Str::slug($name),
            'description' => $this->faker->optional()->sentence(),
            'is_active'   => true,
            'is_system'   => false,
        ];
    }

    public function system(): static
    {
        return $this->state(fn () => ['is_system' => true]);
    }
}
