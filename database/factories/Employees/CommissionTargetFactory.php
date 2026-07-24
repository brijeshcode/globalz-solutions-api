<?php

namespace Database\Factories\Employees;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Employees\CommissionTarget>
 */
class CommissionTargetFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => $this->faker->words(3, true),
            'date' => $this->faker->dateTimeBetween('-1 year', 'now'),
            'note' => $this->faker->optional()->sentence(),
        ];
    }
}
