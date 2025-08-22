<?php

namespace Database\Seeders;

use App\Models\Setups\ItemGroup;
use App\Models\User;
use Illuminate\Database\Seeder;

class ItemGroupSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first() ?? User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);

        $groups = [
            ['name' => 'High Value Items', 'description' => 'Items with high monetary value requiring special handling'],
            ['name' => 'Fragile Items', 'description' => 'Delicate items requiring careful handling and packaging'],
            ['name' => 'Hazardous Materials', 'description' => 'Items requiring special safety protocols'],
            ['name' => 'Perishable Goods', 'description' => 'Items with limited shelf life'],
            ['name' => 'Bulk Items', 'description' => 'Items typically sold in large quantities'],
            ['name' => 'Fast Moving', 'description' => 'High turnover items with frequent sales'],
            ['name' => 'Slow Moving', 'description' => 'Items with low turnover rates'],
            ['name' => 'Seasonal Items', 'description' => 'Products with seasonal demand patterns'],
            ['name' => 'Core Products', 'description' => 'Main products that drive business revenue'],
            ['name' => 'Accessories', 'description' => 'Supporting products and add-ons'],
            ['name' => 'Promotional Items', 'description' => 'Items used for marketing and promotions'],
            ['name' => 'Special Order', 'description' => 'Items ordered specifically for customers'],
            ['name' => 'Discontinued', 'description' => 'Items no longer in regular production'],
            ['name' => 'New Arrivals', 'description' => 'Recently added items to inventory'],
            ['name' => 'Best Sellers', 'description' => 'Top performing products by sales volume'],
        ];

        foreach ($groups as $groupData) {
            ItemGroup::updateOrCreate(
                ['name' => $groupData['name']], 
                array_merge($groupData, [
                    'is_active' => true,
                    'created_by' => $user->id,
                    'updated_by' => $user->id
                ])
            );
        }

        $this->command->info('Created ' . count($groups) . ' item groups.');
    }
}