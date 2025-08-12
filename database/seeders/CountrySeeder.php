<?php

namespace Database\Seeders;

use App\Models\Setups\Country;
use App\Models\User;
use Illuminate\Database\Seeder;

class CountrySeeder extends Seeder
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

        $countries = [
            // North America
            ['name' => 'United States', 'code' => 'USA', 'iso2' => 'US', 'phone_code' => '+1', 'currency' => 'USD'],
            ['name' => 'Canada', 'code' => 'CAN', 'iso2' => 'CA', 'phone_code' => '+1', 'currency' => 'CAD'],
            ['name' => 'Mexico', 'code' => 'MEX', 'iso2' => 'MX', 'phone_code' => '+52', 'currency' => 'MXN'],

            // Europe
            ['name' => 'United Kingdom', 'code' => 'GBR', 'iso2' => 'GB', 'phone_code' => '+44', 'currency' => 'GBP'],
            ['name' => 'Germany', 'code' => 'DEU', 'iso2' => 'DE', 'phone_code' => '+49', 'currency' => 'EUR'],
            ['name' => 'France', 'code' => 'FRA', 'iso2' => 'FR', 'phone_code' => '+33', 'currency' => 'EUR'],
            ['name' => 'Italy', 'code' => 'ITA', 'iso2' => 'IT', 'phone_code' => '+39', 'currency' => 'EUR'],
            ['name' => 'Spain', 'code' => 'ESP', 'iso2' => 'ES', 'phone_code' => '+34', 'currency' => 'EUR'],
            ['name' => 'Netherlands', 'code' => 'NLD', 'iso2' => 'NL', 'phone_code' => '+31', 'currency' => 'EUR'],
            ['name' => 'Belgium', 'code' => 'BEL', 'iso2' => 'BE', 'phone_code' => '+32', 'currency' => 'EUR'],
            ['name' => 'Switzerland', 'code' => 'CHE', 'iso2' => 'CH', 'phone_code' => '+41', 'currency' => 'CHF'],
            ['name' => 'Austria', 'code' => 'AUT', 'iso2' => 'AT', 'phone_code' => '+43', 'currency' => 'EUR'],
            ['name' => 'Sweden', 'code' => 'SWE', 'iso2' => 'SE', 'phone_code' => '+46', 'currency' => 'SEK'],
            ['name' => 'Norway', 'code' => 'NOR', 'iso2' => 'NO', 'phone_code' => '+47', 'currency' => 'NOK'],
            ['name' => 'Denmark', 'code' => 'DNK', 'iso2' => 'DK', 'phone_code' => '+45', 'currency' => 'DKK'],
            ['name' => 'Finland', 'code' => 'FIN', 'iso2' => 'FI', 'phone_code' => '+358', 'currency' => 'EUR'],
            ['name' => 'Poland', 'code' => 'POL', 'iso2' => 'PL', 'phone_code' => '+48', 'currency' => 'PLN'],
            ['name' => 'Czech Republic', 'code' => 'CZE', 'iso2' => 'CZ', 'phone_code' => '+420', 'currency' => 'CZK'],
            ['name' => 'Russia', 'code' => 'RUS', 'iso2' => 'RU', 'phone_code' => '+7', 'currency' => 'RUB'],

            // Asia
            ['name' => 'China', 'code' => 'CHN', 'iso2' => 'CN', 'phone_code' => '+86', 'currency' => 'CNY'],
            ['name' => 'Japan', 'code' => 'JPN', 'iso2' => 'JP', 'phone_code' => '+81', 'currency' => 'JPY'],
            ['name' => 'India', 'code' => 'IND', 'iso2' => 'IN', 'phone_code' => '+91', 'currency' => 'INR'],
            ['name' => 'South Korea', 'code' => 'KOR', 'iso2' => 'KR', 'phone_code' => '+82', 'currency' => 'KRW'],
            ['name' => 'Singapore', 'code' => 'SGP', 'iso2' => 'SG', 'phone_code' => '+65', 'currency' => 'SGD'],
            ['name' => 'Malaysia', 'code' => 'MYS', 'iso2' => 'MY', 'phone_code' => '+60', 'currency' => 'MYR'],
            ['name' => 'Thailand', 'code' => 'THA', 'iso2' => 'TH', 'phone_code' => '+66', 'currency' => 'THB'],
            ['name' => 'Indonesia', 'code' => 'IDN', 'iso2' => 'ID', 'phone_code' => '+62', 'currency' => 'IDR'],
            ['name' => 'Philippines', 'code' => 'PHL', 'iso2' => 'PH', 'phone_code' => '+63', 'currency' => 'PHP'],
            ['name' => 'Vietnam', 'code' => 'VNM', 'iso2' => 'VN', 'phone_code' => '+84', 'currency' => 'VND'],
            ['name' => 'Taiwan', 'code' => 'TWN', 'iso2' => 'TW', 'phone_code' => '+886', 'currency' => 'TWD'],
            ['name' => 'Hong Kong', 'code' => 'HKG', 'iso2' => 'HK', 'phone_code' => '+852', 'currency' => 'HKD'],

            // Middle East
            ['name' => 'United Arab Emirates', 'code' => 'ARE', 'iso2' => 'AE', 'phone_code' => '+971', 'currency' => 'AED'],
            ['name' => 'Saudi Arabia', 'code' => 'SAU', 'iso2' => 'SA', 'phone_code' => '+966', 'currency' => 'SAR'],
            ['name' => 'Israel', 'code' => 'ISR', 'iso2' => 'IL', 'phone_code' => '+972', 'currency' => 'ILS'],
            ['name' => 'Turkey', 'code' => 'TUR', 'iso2' => 'TR', 'phone_code' => '+90', 'currency' => 'TRY'],

            // Oceania
            ['name' => 'Australia', 'code' => 'AUS', 'iso2' => 'AU', 'phone_code' => '+61', 'currency' => 'AUD'],
            ['name' => 'New Zealand', 'code' => 'NZL', 'iso2' => 'NZ', 'phone_code' => '+64', 'currency' => 'NZD'],

            // South America
            ['name' => 'Brazil', 'code' => 'BRA', 'iso2' => 'BR', 'phone_code' => '+55', 'currency' => 'BRL'],
            ['name' => 'Argentina', 'code' => 'ARG', 'iso2' => 'AR', 'phone_code' => '+54', 'currency' => 'ARS'],
            ['name' => 'Chile', 'code' => 'CHL', 'iso2' => 'CL', 'phone_code' => '+56', 'currency' => 'CLP'],
            ['name' => 'Colombia', 'code' => 'COL', 'iso2' => 'CO', 'phone_code' => '+57', 'currency' => 'COP'],
            ['name' => 'Peru', 'code' => 'PER', 'iso2' => 'PE', 'phone_code' => '+51', 'currency' => 'PEN'],

            // Africa
            ['name' => 'South Africa', 'code' => 'ZAF', 'iso2' => 'ZA', 'phone_code' => '+27', 'currency' => 'ZAR'],
            ['name' => 'Nigeria', 'code' => 'NGA', 'iso2' => 'NG', 'phone_code' => '+234', 'currency' => 'NGN'],
            ['name' => 'Egypt', 'code' => 'EGY', 'iso2' => 'EG', 'phone_code' => '+20', 'currency' => 'EGP'],
            ['name' => 'Kenya', 'code' => 'KEN', 'iso2' => 'KE', 'phone_code' => '+254', 'currency' => 'KES'],
            ['name' => 'Morocco', 'code' => 'MAR', 'iso2' => 'MA', 'phone_code' => '+212', 'currency' => 'MAD'],
        ];

        foreach ($countries as $countryData) {
            Country::updateOrCreate(['code' => $countryData['code']], array_merge($countryData, ['is_active' => true, 'created_by' => $user->id, 'updated_by' => $user->id]));
        }

        $this->command->info('Created ' . count($countries) . ' countries.');
    }
}