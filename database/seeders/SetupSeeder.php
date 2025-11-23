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

        // Core setup data (currencies and countries)
        $this->call(CurrencySeeder::class);

        // Employee setup data
        $this->call(DepartmentSeeder::class);

        // Run testing setup only in local environment
        if (app()->environment('local')) {
            $this->command->info('Running testing setup for local environment...');
            $this->testingSetup();
        }

        $this->command->info('Setup seeders completed successfully!');
    }

    private function testingSetup() : void 
    {
        $this->call(CountrySeeder::class);
        $this->call(CurrencyRateSeeder::class);


        // Customer setup data
        $this->call(CustomerSetupSeeder::class);
        
        // Item-related setup data
        $this->call(ItemUnitSeeder::class);
        $this->call(ItemTypeSeeder::class);
        $this->call(ItemFamilySeeder::class);
        $this->call(ItemGroupSeeder::class);
        $this->call(ItemBrandSeeder::class);
        $this->call(ItemCategorySeeder::class);
        $this->call(ItemProfitMarginSeeder::class);
        
        // Tax setup data
        $this->call(TaxCodeSeeder::class);
        
        // Account setup data
        $this->call(AccountTypeSeeder::class);
        
        // Expense setup data
        $this->call(ExpenseCategorySeeder::class);
        
        // Warehouse and supplier setup data
        $this->call(WarehouseSeeder::class);
        $this->call(SupplierTypeSeeder::class);
        $this->call(SupplierPaymentTermSeeder::class);
    }
}