<?php

namespace Database\Seeders;

use App\Models\Setups\ItemUnit;
use App\Models\User;
use Illuminate\Database\Seeder;

class ItemUnitSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::first() ?? User::factory()->create([
            'name' => 'Admin User',
            'email' => 'admin@example.com',
        ]);

        $units = [
            ['name' => 'Piece', 'short_name' => 'PCS', 'description' => 'Individual units or pieces'],
            ['name' => 'Kilogram', 'short_name' => 'KG', 'description' => 'Weight unit in kilograms'],
            ['name' => 'Gram', 'short_name' => 'G', 'description' => 'Weight unit in grams'],
            ['name' => 'Liter', 'short_name' => 'L', 'description' => 'Volume unit in liters'],
            ['name' => 'Milliliter', 'short_name' => 'ML', 'description' => 'Volume unit in milliliters'],
            ['name' => 'Meter', 'short_name' => 'M', 'description' => 'Length unit in meters'],
            ['name' => 'Centimeter', 'short_name' => 'CM', 'description' => 'Length unit in centimeters'],
            ['name' => 'Box', 'short_name' => 'BOX', 'description' => 'Packaging unit - box'],
            ['name' => 'Pack', 'short_name' => 'PACK', 'description' => 'Packaging unit - pack'],
            ['name' => 'Dozen', 'short_name' => 'DOZ', 'description' => 'Set of 12 units'],
            ['name' => 'Set', 'short_name' => 'SET', 'description' => 'Complete set of items'],
            ['name' => 'Pair', 'short_name' => 'PAIR', 'description' => 'Set of two items'],
            ['name' => 'Square Meter', 'short_name' => 'SQM', 'description' => 'Area unit in square meters'],
            ['name' => 'Cubic Meter', 'short_name' => 'CBM', 'description' => 'Volume unit in cubic meters'],
            ['name' => 'Roll', 'short_name' => 'ROLL', 'description' => 'Rolled material unit'],
        ];

        foreach ($units as $unitData) {
            ItemUnit::updateOrCreate(
                ['name' => $unitData['name']], 
                array_merge($unitData, [
                    'is_active' => true,
                    'created_by' => $user->id,
                    'updated_by' => $user->id
                ])
            );
        }

        $this->command->info('Created ' . count($units) . ' item units.');
    }
}