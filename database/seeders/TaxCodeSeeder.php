<?php

namespace Database\Seeders;

use App\Models\Setups\TaxCode;
use App\Models\User;
use Illuminate\Database\Seeder;

class TaxCodeSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first() ?? User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);

        $taxCodes = [
            [
                'code' => 'NOTAX',
                'name' => 'No Tax',
                'description' => 'Tax-exempt items or zero tax rate',
                'tax_percent' => 0.00,
                'type' => 'exclusive',
                'is_default' => true,
            ],
            [
                'code' => 'VAT15',
                'name' => 'VAT 15%',
                'description' => 'Standard VAT rate of 15%',
                'tax_percent' => 15.00,
                'type' => 'exclusive',
                'is_default' => false,
            ],
            [
                'code' => 'VAT10',
                'name' => 'VAT 10%',
                'description' => 'Reduced VAT rate of 10%',
                'tax_percent' => 10.00,
                'type' => 'exclusive',
                'is_default' => false,
            ],
            [
                'code' => 'VAT5',
                'name' => 'VAT 5%',
                'description' => 'Low VAT rate of 5%',
                'tax_percent' => 5.00,
                'type' => 'exclusive',
                'is_default' => false,
            ],
            [
                'code' => 'GST15',
                'name' => 'GST 15%',
                'description' => 'Goods and Services Tax at 15%',
                'tax_percent' => 15.00,
                'type' => 'inclusive',
                'is_default' => false,
            ],
            [
                'code' => 'GST10',
                'name' => 'GST 10%',
                'description' => 'Goods and Services Tax at 10%',
                'tax_percent' => 10.00,
                'type' => 'inclusive',
                'is_default' => false,
            ],
            [
                'code' => 'SALES8',
                'name' => 'Sales Tax 8%',
                'description' => 'Sales tax at 8%',
                'tax_percent' => 8.00,
                'type' => 'exclusive',
                'is_default' => false,
            ],
            [
                'code' => 'SALES6',
                'name' => 'Sales Tax 6%',
                'description' => 'Sales tax at 6%',
                'tax_percent' => 6.00,
                'type' => 'exclusive',
                'is_default' => false,
            ],
            [
                'code' => 'EXEMPT',
                'name' => 'Tax Exempt',
                'description' => 'Completely tax-exempt items',
                'tax_percent' => 0.00,
                'type' => 'exclusive',
                'is_default' => false,
            ],
            [
                'code' => 'LUXURY',
                'name' => 'Luxury Tax 25%',
                'description' => 'High tax rate for luxury items',
                'tax_percent' => 25.00,
                'type' => 'exclusive',
                'is_default' => false,
            ],
        ];

        foreach ($taxCodes as $taxCodeData) {
            TaxCode::updateOrCreate(
                ['code' => $taxCodeData['code']], 
                array_merge($taxCodeData, [
                    'is_active' => true,
                    'created_by' => $user->id,
                    'updated_by' => $user->id
                ])
            );
        }

        $this->command->info('Created ' . count($taxCodes) . ' tax codes.');
    }
}