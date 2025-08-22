<?php

namespace Database\Seeders;

use App\Models\Setups\ItemProfitMargin;
use App\Models\User;
use Illuminate\Database\Seeder;

class ItemProfitMarginSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first() ?? User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);

        $margins = [
            [
                'name' => 'Standard Margin',
                'margin_percentage' => 25.00,
                'description' => 'Standard profit margin for regular products',
            ],
            [
                'name' => 'High Margin',
                'margin_percentage' => 50.00,
                'description' => 'High profit margin for premium products',
            ],
            [
                'name' => 'Low Margin',
                'margin_percentage' => 10.00,
                'description' => 'Low profit margin for competitive products',
            ],
            [
                'name' => 'Premium Margin',
                'margin_percentage' => 75.00,
                'description' => 'Premium profit margin for luxury items',
            ],
            [
                'name' => 'Bulk Margin',
                'margin_percentage' => 15.00,
                'description' => 'Reduced margin for bulk sales',
            ],
            [
                'name' => 'Clearance Margin',
                'margin_percentage' => 5.00,
                'description' => 'Minimal margin for clearance items',
            ],
            [
                'name' => 'Electronics Margin',
                'margin_percentage' => 20.00,
                'description' => 'Typical margin for electronic products',
            ],
            [
                'name' => 'Software Margin',
                'margin_percentage' => 80.00,
                'description' => 'High margin for software and digital products',
            ],
            [
                'name' => 'Service Margin',
                'margin_percentage' => 60.00,
                'description' => 'Profit margin for service-based items',
            ],
            [
                'name' => 'Wholesale Margin',
                'margin_percentage' => 8.00,
                'description' => 'Low margin for wholesale pricing',
            ],
            [
                'name' => 'Retail Margin',
                'margin_percentage' => 35.00,
                'description' => 'Standard retail profit margin',
            ],
            [
                'name' => 'Cost Plus',
                'margin_percentage' => 0.00,
                'description' => 'Cost plus pricing with no fixed margin',
            ],
        ];

        foreach ($margins as $marginData) {
            ItemProfitMargin::updateOrCreate(
                ['name' => $marginData['name']], 
                array_merge($marginData, [
                    'is_active' => true,
                    'created_by' => $user->id,
                    'updated_by' => $user->id
                ])
            );
        }

        $this->command->info('Created ' . count($margins) . ' item profit margins.');
    }
}