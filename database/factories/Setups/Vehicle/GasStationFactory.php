<?php

namespace Database\Factories\Setups\Vehicle;

use App\Models\Setups\Vehicle\GasStation;
use Illuminate\Database\Eloquent\Factories\Factory;

class GasStationFactory extends Factory
{
    protected $model = GasStation::class;

    public function definition(): array
    {
        return [
            'name'    => $this->faker->company() . ' Gas Station',
            'balance' => 0,
            'address' => $this->faker->address(),
            'note'    => $this->faker->optional()->sentence(),
        ];
    }

    public function withBalance(float $balance): static
    {
        return $this->state(['balance' => $balance]);
    }
}
