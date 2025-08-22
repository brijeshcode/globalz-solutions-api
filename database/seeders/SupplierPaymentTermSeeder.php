<?php

namespace Database\Seeders;

use App\Models\Setups\SupplierPaymentTerm;
use App\Models\User;
use Illuminate\Database\Seeder;

class SupplierPaymentTermSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first() ?? User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);

        $paymentTerms = [
            [
                'name' => 'Net 30',
                'days' => 30,
                'type' => 'net',
                'discount_percentage' => null,
                'discount_days' => null,
                'description' => 'Payment due within 30 days of invoice date',
            ],
            [
                'name' => 'Net 15',
                'days' => 15,
                'type' => 'net',
                'discount_percentage' => null,
                'discount_days' => null,
                'description' => 'Payment due within 15 days of invoice date',
            ],
            [
                'name' => 'Net 60',
                'days' => 60,
                'type' => 'net',
                'discount_percentage' => null,
                'discount_days' => null,
                'description' => 'Payment due within 60 days of invoice date',
            ],
            [
                'name' => 'Net 7',
                'days' => 7,
                'type' => 'net',
                'discount_percentage' => null,
                'discount_days' => null,
                'description' => 'Payment due within 7 days of invoice date',
            ],
            [
                'name' => '2/10 Net 30',
                'days' => 30,
                'type' => 'net',
                'discount_percentage' => 2.00,
                'discount_days' => 10,
                'description' => '2% discount if paid within 10 days, otherwise due in 30 days',
            ],
            [
                'name' => '1/10 Net 30',
                'days' => 30,
                'type' => 'net',
                'discount_percentage' => 1.00,
                'discount_days' => 10,
                'description' => '1% discount if paid within 10 days, otherwise due in 30 days',
            ],
            [
                'name' => '3/15 Net 45',
                'days' => 45,
                'type' => 'net',
                'discount_percentage' => 3.00,
                'discount_days' => 15,
                'description' => '3% discount if paid within 15 days, otherwise due in 45 days',
            ],
            [
                'name' => 'COD',
                'days' => 0,
                'type' => 'cash_on_delivery',
                'discount_percentage' => null,
                'discount_days' => null,
                'description' => 'Cash on Delivery - payment due upon receipt',
            ],
            [
                'name' => 'Prepaid',
                'days' => -1,
                'type' => 'advance',
                'discount_percentage' => null,
                'discount_days' => null,
                'description' => 'Payment required before shipment',
            ],
            [
                'name' => 'Net 90',
                'days' => 90,
                'type' => 'net',
                'discount_percentage' => null,
                'discount_days' => null,
                'description' => 'Payment due within 90 days of invoice date',
            ],
            [
                'name' => 'Net 120',
                'days' => 120,
                'type' => 'credit',
                'discount_percentage' => null,
                'discount_days' => null,
                'description' => 'Payment due within 120 days of invoice date',
            ],
            [
                'name' => 'Due on Receipt',
                'days' => 0,
                'type' => 'due_on_receipt',
                'discount_percentage' => null,
                'discount_days' => null,
                'description' => 'Payment due immediately upon receipt of invoice',
            ],
        ];

        foreach ($paymentTerms as $termData) {
            SupplierPaymentTerm::updateOrCreate(
                ['name' => $termData['name']], 
                array_merge($termData, [
                    'is_active' => true,
                    'created_by' => $user->id,
                    'updated_by' => $user->id
                ])
            );
        }

        $this->command->info('Created ' . count($paymentTerms) . ' supplier payment terms.');
    }
}