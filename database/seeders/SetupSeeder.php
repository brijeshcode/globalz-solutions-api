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
        $this->call(CountrySeeder::class);
        
        // Employee setup data
        $this->call(DepartmentSeeder::class);
        
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
        
        // Warehouse and supplier setup data
        $this->call(WarehouseSeeder::class);
        $this->call(SupplierTypeSeeder::class);
        $this->call(SupplierPaymentTermSeeder::class);

        $this->command->info('Setup seeders completed successfully!');
    }
}