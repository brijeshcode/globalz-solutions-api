<?php

namespace Database\Seeders;

use App\Models\Setups\ItemBrand;
use App\Models\User;
use Illuminate\Database\Seeder;

class ItemBrandSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first() ?? User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);

        $brands = [
            ['name' => 'Apple', 'description' => 'Technology and consumer electronics'],
            ['name' => 'Samsung', 'description' => 'Electronics and technology products'],
            ['name' => 'Microsoft', 'description' => 'Software and technology solutions'],
            ['name' => 'Dell', 'description' => 'Computer hardware and technology'],
            ['name' => 'HP', 'description' => 'Computer hardware and printing solutions'],
            ['name' => 'Lenovo', 'description' => 'Computer and technology products'],
            ['name' => 'Canon', 'description' => 'Imaging and optical products'],
            ['name' => 'Epson', 'description' => 'Printing and imaging solutions'],
            ['name' => 'Sony', 'description' => 'Electronics and entertainment products'],
            ['name' => 'LG', 'description' => 'Electronics and home appliances'],
            ['name' => 'Nike', 'description' => 'Sports apparel and equipment'],
            ['name' => 'Adidas', 'description' => 'Sports and lifestyle products'],
            ['name' => 'IKEA', 'description' => 'Home furniture and accessories'],
            ['name' => 'Staples', 'description' => 'Office supplies and stationery'],
            ['name' => '3M', 'description' => 'Industrial and consumer products'],
            ['name' => 'Generic', 'description' => 'Generic or unbranded products'],
            ['name' => 'Private Label', 'description' => 'Company private label products'],
            ['name' => 'Logitech', 'description' => 'Computer peripherals and accessories'],
            ['name' => 'Brother', 'description' => 'Printing and office equipment'],
            ['name' => 'Cisco', 'description' => 'Networking and communication equipment'],
        ];

        foreach ($brands as $brandData) {
            ItemBrand::updateOrCreate(
                ['name' => $brandData['name']], 
                array_merge($brandData, [
                    'is_active' => true,
                    'created_by' => $user->id,
                    'updated_by' => $user->id
                ])
            );
        }

        $this->command->info('Created ' . count($brands) . ' item brands.');
    }
}