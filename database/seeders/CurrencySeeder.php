<?php

namespace Database\Seeders;

use App\Models\Setups\Generals\Currencies\Currency;
use App\Models\User;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get first user for created_by/updated_by, or create admin user
        $user = User::first() ?? User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);

        $currencies = [
            [
                'name' => 'US Dollar',
                'code' => 'USD',
                'symbol' => '$',
                'symbol_position' => 'before',
                'decimal_places' => 2,
                'decimal_separator' => '.',
                'thousand_separator' => ',',
                'is_active' => true,
            ],
            [
                'name' => 'Euro',
                'code' => 'EUR',
                'symbol' => '€',
                'symbol_position' => 'before',
                'decimal_places' => 2,
                'decimal_separator' => ',',
                'thousand_separator' => '.',
                'calculation_type' => 'multiply',
                'is_active' => true,
            ],
            [
                'name' => 'British Pound',
                'code' => 'GBP',
                'symbol' => '£',
                'symbol_position' => 'before',
                'decimal_places' => 2,
                'calculation_type' => 'multiply',
                'decimal_separator' => '.',
                'thousand_separator' => ',',
                'is_active' => true,
            ],
            [
                'name' => 'Lebanese pound',
                'code' => 'LBP',
                'symbol' => 'LL',
                'symbol_position' => 'before',
                'decimal_places' => 2,
                'decimal_separator' => '.',
                'calculation_type' => 'divide',
                'thousand_separator' => ',',
                'is_active' => true,
            ],
            // [
            //     'name' => 'Japanese Yen',
            //     'code' => 'JPY',
            //     'symbol' => '¥',
            //     'symbol_position' => 'before',
            //     'decimal_places' => 0,
            //     'decimal_separator' => '.',
            //     'thousand_separator' => ',',
            //     'is_active' => true,
            // ],
            // [
            //     'name' => 'Canadian Dollar',
            //     'code' => 'CAD',
            //     'symbol' => 'C$',
            //     'symbol_position' => 'before',
            //     'decimal_places' => 2,
            //     'decimal_separator' => '.',
            //     'thousand_separator' => ',',
            //     'is_active' => true,
            // ],
            // [
            //     'name' => 'Australian Dollar',
            //     'code' => 'AUD',
            //     'symbol' => 'A$',
            //     'symbol_position' => 'before',
            //     'decimal_places' => 2,
            //     'decimal_separator' => '.',
            //     'thousand_separator' => ',',
            //     'is_active' => true,
            // ],
            // [
            //     'name' => 'Swiss Franc',
            //     'code' => 'CHF',
            //     'symbol' => 'CHF',
            //     'symbol_position' => 'before',
            //     'decimal_places' => 2,
            //     'decimal_separator' => '.',
            //     'thousand_separator' => ',',
            //     'is_active' => true,
            // ],
            // [
            //     'name' => 'Chinese Yuan',
            //     'code' => 'CNY',
            //     'symbol' => '¥',
            //     'symbol_position' => 'before',
            //     'decimal_places' => 2,
            //     'decimal_separator' => '.',
            //     'thousand_separator' => ',',
            //     'is_active' => true,
            // ],
            // [
            //     'name' => 'Indian Rupee',
            //     'code' => 'INR',
            //     'symbol' => '₹',
            //     'symbol_position' => 'before',
            //     'decimal_places' => 2,
            //     'decimal_separator' => '.',
            //     'thousand_separator' => ',',
            //     'is_active' => true,
            // ],
            // [
            //     'name' => 'Mexican Peso',
            //     'code' => 'MXN',
            //     'symbol' => '$',
            //     'symbol_position' => 'before',
            //     'decimal_places' => 2,
            //     'decimal_separator' => '.',
            //     'thousand_separator' => ',',
            //     'is_active' => true,
            // ],
            // [
            //     'name' => 'Brazilian Real',
            //     'code' => 'BRL',
            //     'symbol' => 'R$',
            //     'symbol_position' => 'before',
            //     'decimal_places' => 2,
            //     'decimal_separator' => ',',
            //     'thousand_separator' => '.',
            //     'is_active' => true,
            // ],
            // [
            //     'name' => 'South African Rand',
            //     'code' => 'ZAR',
            //     'symbol' => 'R',
            //     'symbol_position' => 'before',
            //     'decimal_places' => 2,
            //     'decimal_separator' => '.',
            //     'thousand_separator' => ',',
            //     'is_active' => true,
            // ],
            // [
            //     'name' => 'Russian Ruble',
            //     'code' => 'RUB',
            //     'symbol' => '₽',
            //     'symbol_position' => 'after',
            //     'decimal_places' => 2,
            //     'decimal_separator' => ',',
            //     'thousand_separator' => ' ',
            //     'is_active' => true,
            // ],
            // [
            //     'name' => 'Korean Won',
            //     'code' => 'KRW',
            //     'symbol' => '₩',
            //     'symbol_position' => 'before',
            //     'decimal_places' => 0,
            //     'decimal_separator' => '.',
            //     'thousand_separator' => ',',
            //     'is_active' => true,
            // ],
            // [
            //     'name' => 'Singapore Dollar',
            //     'code' => 'SGD',
            //     'symbol' => 'S$',
            //     'symbol_position' => 'before',
            //     'decimal_places' => 2,
            //     'decimal_separator' => '.',
            //     'thousand_separator' => ',',
            //     'is_active' => true,
            // ],
        ];

        foreach ($currencies as $currencyData) {
             
            Currency::updateOrCreate(['code' => $currencyData['code']], array_merge($currencyData, ['created_by' => $user->id, 'updated_by' => $user->id]));
        }

        $this->command->info('Created ' . count($currencies) . ' currencies.');
    }
}