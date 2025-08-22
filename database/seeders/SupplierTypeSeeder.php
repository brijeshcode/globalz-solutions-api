<?php

namespace Database\Seeders;

use App\Models\Setups\SupplierType;
use App\Models\User;
use Illuminate\Database\Seeder;

class SupplierTypeSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first() ?? User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);

        $supplierTypes = [
            ['name' => 'Manufacturer', 'description' => 'Companies that produce goods directly'],
            ['name' => 'Distributor', 'description' => 'Companies that distribute products from manufacturers'],
            ['name' => 'Wholesaler', 'description' => 'Companies that sell products in bulk quantities'],
            ['name' => 'Retailer', 'description' => 'Companies that sell products to end consumers'],
            ['name' => 'Service Provider', 'description' => 'Companies that provide services rather than goods'],
            ['name' => 'Contractor', 'description' => 'Independent contractors and subcontractors'],
            ['name' => 'Consultant', 'description' => 'Professional consulting services providers'],
            ['name' => 'Vendor', 'description' => 'General vendors providing various products or services'],
            ['name' => 'Raw Material Supplier', 'description' => 'Suppliers of raw materials for manufacturing'],
            ['name' => 'Component Supplier', 'description' => 'Suppliers of components and parts'],
            ['name' => 'Packaging Supplier', 'description' => 'Companies providing packaging materials and services'],
            ['name' => 'Logistics Provider', 'description' => 'Transportation and logistics service providers'],
            ['name' => 'Technology Partner', 'description' => 'IT and technology solution providers'],
            ['name' => 'Maintenance Provider', 'description' => 'Equipment and facility maintenance services'],
            ['name' => 'Professional Services', 'description' => 'Legal, accounting, and other professional services'],
        ];

        foreach ($supplierTypes as $typeData) {
            SupplierType::updateOrCreate(
                ['name' => $typeData['name']], 
                array_merge($typeData, [
                    'is_active' => true,
                    'created_by' => $user->id,
                    'updated_by' => $user->id
                ])
            );
        }

        $this->command->info('Created ' . count($supplierTypes) . ' supplier types.');
    }
}