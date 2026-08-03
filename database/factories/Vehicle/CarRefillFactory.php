<?php

namespace Database\Factories\Vehicle;

use App\Models\Employees\Employee;
use App\Models\Vehicle\Car;
use App\Models\Vehicle\CarRefill;
use App\Models\Vehicle\GasStation;
use Illuminate\Database\Eloquent\Factories\Factory;

class CarRefillFactory extends Factory
{
    protected $model = CarRefill::class;

    public function definition(): array
    {
        return [
            'date'           => $this->faker->dateTimeBetween('-1 year', 'now'),
            'car_id'         => Car::factory(),
            'gas_station_id' => GasStation::factory(),
            'driver_id'      => Employee::factory(),
            'odometer'       => $this->faker->numberBetween(1000, 200000),
            'km_driven'      => 0,
            'amount'         => $this->faker->randomFloat(2, 10, 200),
            'invoices_count' => $this->faker->optional()->numberBetween(1, 50),
            'note'           => $this->faker->optional()->sentence(),
        ];
    }
}
