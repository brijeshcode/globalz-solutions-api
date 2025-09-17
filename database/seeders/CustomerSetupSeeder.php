<?php

namespace Database\Seeders;

use App\Models\Setups\Customers\CustomerGroup;
use App\Models\Setups\Customers\CustomerType;
use App\Models\Setups\Customers\CustomerZone;
use App\Models\Setups\Customers\CustomerPaymentTerm;
use App\Models\Setups\Customers\CustomerProvince;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CustomerSetupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Creating customer setup data...');

        $users = User::all();
        $createdBy = $users->count() > 0 ? $users->random()->id : null;

        DB::transaction(function () use ($createdBy) {
            // Customer Types
            $customerTypes = [
                [
                    'name' => 'Retail',
                    'description' => 'Individual retail customers and small businesses',
                    'is_active' => true,
                ],
                [
                    'name' => 'Wholesale',
                    'description' => 'Wholesale customers buying in bulk quantities',
                    'is_active' => true,
                ],
                [
                    'name' => 'Corporate',
                    'description' => 'Large corporate clients with enterprise needs',
                    'is_active' => true,
                ]
            ];

            foreach ($customerTypes as $typeData) {
                $type = CustomerType::firstOrCreate(
                    ['name' => $typeData['name']],
                    array_merge($typeData, ['created_by' => $createdBy])
                );
                $this->command->info("Created customer type: {$type->name}");
            }

            // Customer Groups
            $customerGroups = [
                [
                    'name' => 'Premium',
                    'description' => 'High-value customers with special privileges',
                    'is_active' => true,
                ],
                [
                    'name' => 'Standard',
                    'description' => 'Regular customers with standard terms',
                    'is_active' => true,
                ],
                [
                    'name' => 'New Customer',
                    'description' => 'Recently acquired customers in probation period',
                    'is_active' => true,
                ]
            ];

            foreach ($customerGroups as $groupData) {
                $group = CustomerGroup::firstOrCreate(
                    ['name' => $groupData['name']],
                    array_merge($groupData, ['created_by' => $createdBy])
                );
                $this->command->info("Created customer group: {$group->name}");
            }

            // Customer Zones
            $customerZones = [
                [
                    'name' => 'North Zone',
                    'description' => 'Northern region coverage area',
                    'is_active' => true,
                ],
                [
                    'name' => 'South Zone',
                    'description' => 'Southern region coverage area',
                    'is_active' => true,
                ],
                [
                    'name' => 'Central Zone',
                    'description' => 'Central region coverage area',
                    'is_active' => true,
                ]
            ];

            foreach ($customerZones as $zoneData) {
                $zone = CustomerZone::firstOrCreate(
                    ['name' => $zoneData['name']],
                    array_merge($zoneData, ['created_by' => $createdBy])
                );
                $this->command->info("Created customer zone: {$zone->name}");
            }

            // Customer Payment Terms
            $paymentTerms = [
                [
                    'name' => 'Net 30',
                    'description' => 'Payment due within 30 days',
                    'days' => 30,
                    'type' => 'net',
                    'discount_percentage' => 0.00,
                    'discount_days' => 0,
                    'is_active' => true,
                ],
                [
                    'name' => '2/10 Net 30',
                    'description' => '2% discount if paid within 10 days, otherwise net 30',
                    'days' => 30,
                    'type' => 'discount',
                    'discount_percentage' => 2.00,
                    'discount_days' => 10,
                    'is_active' => true,
                ],
                [
                    'name' => 'Cash on Delivery',
                    'description' => 'Payment required upon delivery',
                    'days' => 0,
                    'type' => 'immediate',
                    'discount_percentage' => 0.00,
                    'discount_days' => 0,
                    'is_active' => true,
                ]
            ];

            foreach ($paymentTerms as $termData) {
                $term = CustomerPaymentTerm::firstOrCreate(
                    ['name' => $termData['name']],
                    array_merge($termData, ['created_by' => $createdBy])
                );
                $this->command->info("Created customer payment term: {$term->name}");
            }

            // Customer Provinces
            $provinces = [
                [
                    'name' => 'California',
                    'description' => 'State of California',
                    'code' => 'CA',
                    'country_id' => 1, // Assuming USA has ID 1
                    'is_active' => true,
                ],
                [
                    'name' => 'New York',
                    'description' => 'State of New York',
                    'code' => 'NY',
                    'country_id' => 1, // Assuming USA has ID 1
                    'is_active' => true,
                ],
                [
                    'name' => 'Texas',
                    'description' => 'State of Texas',
                    'code' => 'TX',
                    'country_id' => 1, // Assuming USA has ID 1
                    'is_active' => true,
                ]
            ];

            foreach ($provinces as $provinceData) {
                $province = CustomerProvince::firstOrCreate(
                    ['code' => $provinceData['code']],
                    array_merge($provinceData, ['created_by' => $createdBy])
                );
                $this->command->info("Created customer province: {$province->name} ({$province->code})");
            }
        });

        $this->command->info('Customer setup data created successfully!');
    }
}