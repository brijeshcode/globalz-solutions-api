<?php

namespace Database\Factories\Vehicle;

use App\Models\Vehicle\Car;
use Illuminate\Database\Eloquent\Factories\Factory;

class CarFactory extends Factory
{
    protected $model = Car::class;

    public function definition(): array
    {
        return [
            'name'         => $this->faker->randomElement(['Toyota', 'Ford', 'Nissan']) . ' ' . $this->faker->word(),
            'plate_number' => strtoupper($this->faker->bothify('???-####')),
            'year'         => $this->faker->numberBetween(2010, 2024),
            'color'        => $this->faker->safeColorName(),
            'make'         => $this->faker->randomElement(['Toyota', 'Ford', 'Nissan', 'Mitsubishi']),
            'model'        => $this->faker->word(),
            'note'         => $this->faker->optional()->sentence(),
            'is_active'    => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
