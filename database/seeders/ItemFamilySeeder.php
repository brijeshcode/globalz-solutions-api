<?php

namespace Database\Seeders;

use App\Models\Setups\ItemFamily;
use App\Models\User;
use Illuminate\Database\Seeder;

class ItemFamilySeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first() ?? User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);

        $families = [
            ['name' => 'Electronics', 'description' => 'Electronic devices and components'],
            ['name' => 'Furniture', 'description' => 'Office and home furniture items'],
            ['name' => 'Stationery', 'description' => 'Office supplies and stationery items'],
            ['name' => 'Computer Hardware', 'description' => 'Computer parts and accessories'],
            ['name' => 'Software', 'description' => 'Software licenses and applications'],
            ['name' => 'Clothing', 'description' => 'Apparel and clothing items'],
            ['name' => 'Tools', 'description' => 'Hand tools and equipment'],
            ['name' => 'Books', 'description' => 'Books and educational materials'],
            ['name' => 'Food & Beverages', 'description' => 'Food items and beverages'],
            ['name' => 'Cleaning Supplies', 'description' => 'Cleaning products and supplies'],
            ['name' => 'Medical Supplies', 'description' => 'Medical and healthcare products'],
            ['name' => 'Safety Equipment', 'description' => 'Safety gear and protective equipment'],
            ['name' => 'Automotive', 'description' => 'Car parts and automotive supplies'],
            ['name' => 'Sports & Recreation', 'description' => 'Sports equipment and recreational items'],
            ['name' => 'Home & Garden', 'description' => 'Home improvement and garden supplies'],
        ];

        foreach ($families as $familyData) {
            ItemFamily::updateOrCreate(
                ['name' => $familyData['name']], 
                array_merge($familyData, [
                    'is_active' => true,
                    'created_by' => $user->id,
                    'updated_by' => $user->id
                ])
            );
        }

        $this->command->info('Created ' . count($families) . ' item families.');
    }
}