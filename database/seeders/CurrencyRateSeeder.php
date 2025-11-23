<?php

namespace Database\Seeders;

use App\Models\Setups\Generals\Currencies\currencyRate;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class CurrencyRateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $currencyRates = [
            [
                'currency_id' => 1,
                'rate'=> 1,
                'is_active' => true
            ],
            [
                'currency_id' => 2,
                'rate'=> 1.17,
                'is_active' => true
            ],
            [
                'currency_id' => 3,
                'rate'=> 1.32,
                'is_active' => true
            ],
            [
                'currency_id' => 4,
                'rate'=> 89500,
                'is_active' => true
            ],

        ];

        foreach ($currencyRates as $currencyRate) {
            currencyRate::create($currencyRate);
        }
    }
}
