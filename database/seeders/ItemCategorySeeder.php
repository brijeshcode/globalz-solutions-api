<?php

namespace Database\Seeders;

use App\Models\Setups\ItemCategory;
use App\Models\User;
use Illuminate\Database\Seeder;

class ItemCategorySeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first() ?? User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);

        $categories = [
            ['name' => 'Laptops', 'description' => 'Portable computers and notebooks'],
            ['name' => 'Desktop Computers', 'description' => 'Desktop PCs and workstations'],
            ['name' => 'Monitors', 'description' => 'Computer displays and screens'],
            ['name' => 'Printers', 'description' => 'Printing devices and accessories'],
            ['name' => 'Keyboards', 'description' => 'Computer keyboards and input devices'],
            ['name' => 'Mice', 'description' => 'Computer mice and pointing devices'],
            ['name' => 'Storage Devices', 'description' => 'Hard drives, SSDs, and storage media'],
            ['name' => 'Network Equipment', 'description' => 'Routers, switches, and network devices'],
            ['name' => 'Office Chairs', 'description' => 'Seating furniture for offices'],
            ['name' => 'Desks', 'description' => 'Office desks and workstations'],
            ['name' => 'Filing Cabinets', 'description' => 'Document storage furniture'],
            ['name' => 'Paper Products', 'description' => 'Copy paper, notebooks, and paper supplies'],
            ['name' => 'Writing Instruments', 'description' => 'Pens, pencils, and markers'],
            ['name' => 'Office Supplies', 'description' => 'General office stationery and supplies'],
            ['name' => 'Cleaning Products', 'description' => 'Janitorial and cleaning supplies'],
            ['name' => 'Safety Equipment', 'description' => 'Personal protective equipment'],
            ['name' => 'Break Room Supplies', 'description' => 'Kitchen and break room items'],
            ['name' => 'Software Licenses', 'description' => 'Software applications and licenses'],
            ['name' => 'Cables & Adapters', 'description' => 'Connectivity cables and adapters'],
            ['name' => 'Mobile Devices', 'description' => 'Smartphones and tablets'],
        ];

        foreach ($categories as $categoryData) {
            ItemCategory::updateOrCreate(
                ['name' => $categoryData['name']], 
                array_merge($categoryData, [
                    'is_active' => true,
                    'created_by' => $user->id,
                    'updated_by' => $user->id
                ])
            );
        }

        $this->command->info('Created ' . count($categories) . ' item categories.');
    }
}