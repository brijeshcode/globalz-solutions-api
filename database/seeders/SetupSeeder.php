<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class SetupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Starting setup seeders...');

        // Seed currencies first (countries reference currencies)
        $this->call(CurrencySeeder::class);
        
        // Then seed countries
        $this->call(CountrySeeder::class);

        $this->command->info('Setup seeders completed successfully!');
    }
}