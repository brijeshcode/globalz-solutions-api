<?php

namespace Database\Seeders;

use App\Models\Setups\ItemType;
use App\Models\User;
use Illuminate\Database\Seeder;

class ItemTypeSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first() ?? User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);

        $types = [
            ['name' => 'Inventory', 'description' => 'Physical products that are tracked in inventory'],
            ['name' => 'Non-Inventory', 'description' => 'Items not tracked in inventory like services or supplies'],
            ['name' => 'Service', 'description' => 'Service-based items without physical inventory'],
            ['name' => 'Digital', 'description' => 'Digital products like software, licenses, or downloads'],
            ['name' => 'Raw Material', 'description' => 'Materials used in manufacturing or production'],
            ['name' => 'Finished Goods', 'description' => 'Completed products ready for sale'],
            ['name' => 'Work in Progress', 'description' => 'Items in production process'],
            ['name' => 'Component', 'description' => 'Parts used to build or assemble other products'],
            ['name' => 'Bundle', 'description' => 'Collection of multiple items sold as one unit'],
            ['name' => 'Kit', 'description' => 'Set of items that are assembled by the customer'],
        ];

        foreach ($types as $typeData) {
            ItemType::updateOrCreate(
                ['name' => $typeData['name']], 
                array_merge($typeData, [
                    'is_active' => true,
                    'created_by' => $user->id,
                    'updated_by' => $user->id
                ])
            );
        }

        $this->command->info('Created ' . count($types) . ' item types.');
    }
}