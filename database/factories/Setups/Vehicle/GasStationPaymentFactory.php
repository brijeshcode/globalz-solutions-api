<?php

namespace Database\Factories\Setups\Vehicle;

use App\Models\Accounts\Account;
use App\Models\Setups\Vehicle\GasStation;
use App\Models\Setups\Vehicle\GasStationPayment;
use Illuminate\Database\Eloquent\Factories\Factory;

class GasStationPaymentFactory extends Factory
{
    protected $model = GasStationPayment::class;

    public function definition(): array
    {
        return [
            'date'           => $this->faker->dateTimeBetween('-1 year', 'now'),
            'gas_station_id' => GasStation::factory(),
            'account_id'     => Account::factory(),
            'amount'         => $this->faker->randomFloat(2, 50, 1000),
            'note'           => $this->faker->optional()->sentence(),
        ];
    }
}
