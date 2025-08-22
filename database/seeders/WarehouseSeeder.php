<?php

namespace Database\Seeders;

use App\Models\Setups\Warehouse;
use App\Models\User;
use Illuminate\Database\Seeder;

class WarehouseSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first() ?? User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);

        $warehouses = [
            [
                'name' => 'Main Warehouse',
                'note' => 'Primary storage facility for general inventory',
                'address_line_1' => '123 Industrial Blvd',
                'address_line_2' => 'Building A',
                'city' => 'Los Angeles',
                'state' => 'California',
                'postal_code' => '90001',
                'country' => 'United States',
            ],
            [
                'name' => 'East Coast Distribution Center',
                'note' => 'Regional distribution center serving East Coast customers',
                'address_line_1' => '456 Commerce Drive',
                'address_line_2' => null,
                'city' => 'New York',
                'state' => 'New York',
                'postal_code' => '10001',
                'country' => 'United States',
            ],
            [
                'name' => 'West Coast Fulfillment Center',
                'note' => 'High-volume fulfillment center for online orders',
                'address_line_1' => '789 Logistics Way',
                'address_line_2' => 'Suite 100',
                'city' => 'San Francisco',
                'state' => 'California',
                'postal_code' => '94102',
                'country' => 'United States',
            ],
            [
                'name' => 'Cold Storage Facility',
                'note' => 'Temperature-controlled storage for perishable items',
                'address_line_1' => '321 Refrigeration Rd',
                'address_line_2' => null,
                'city' => 'Chicago',
                'state' => 'Illinois',
                'postal_code' => '60601',
                'country' => 'United States',
            ],
            [
                'name' => 'Returns Processing Center',
                'note' => 'Facility dedicated to handling returned merchandise',
                'address_line_1' => '654 Returns Lane',
                'address_line_2' => 'Building C',
                'city' => 'Dallas',
                'state' => 'Texas',
                'postal_code' => '75201',
                'country' => 'United States',
            ],
            [
                'name' => 'Hazmat Storage',
                'note' => 'Specialized facility for hazardous materials storage',
                'address_line_1' => '987 Safety Street',
                'address_line_2' => null,
                'city' => 'Houston',
                'state' => 'Texas',
                'postal_code' => '77001',
                'country' => 'United States',
            ],
            [
                'name' => 'Electronics Warehouse',
                'note' => 'Climate-controlled storage for electronic components',
                'address_line_1' => '147 Tech Park Ave',
                'address_line_2' => 'Unit 5',
                'city' => 'Seattle',
                'state' => 'Washington',
                'postal_code' => '98101',
                'country' => 'United States',
            ],
            [
                'name' => 'Bulk Storage Facility',
                'note' => 'Large capacity storage for bulk items',
                'address_line_1' => '258 Bulk Storage Blvd',
                'address_line_2' => null,
                'city' => 'Atlanta',
                'state' => 'Georgia',
                'postal_code' => '30301',
                'country' => 'United States',
            ],
        ];

        foreach ($warehouses as $warehouseData) {
            Warehouse::updateOrCreate(
                ['name' => $warehouseData['name']], 
                array_merge($warehouseData, [
                    'is_active' => true,
                    'created_by' => $user->id,
                    'updated_by' => $user->id
                ])
            );
        }

        $this->command->info('Created ' . count($warehouses) . ' warehouses.');
    }
}