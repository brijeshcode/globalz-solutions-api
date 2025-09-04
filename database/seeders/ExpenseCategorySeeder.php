<?php

namespace Database\Seeders;

use App\Models\Setups\Expenses\ExpenseCategory;
use Illuminate\Database\Seeder;

class ExpenseCategorySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $categories = [
            // Root categories
            [
                'parent_id' => null,
                'name' => 'Office Expenses',
                'description' => 'General office related expenses',
                'is_active' => true,
            ],
            [
                'parent_id' => null,
                'name' => 'Travel & Transportation',
                'description' => 'Travel and transportation related expenses',
                'is_active' => true,
            ],
            [
                'parent_id' => null,
                'name' => 'Utilities',
                'description' => 'Utility bills and services',
                'is_active' => true,
            ],
            [
                'parent_id' => null,
                'name' => 'Marketing & Advertising',
                'description' => 'Marketing and advertising expenses',
                'is_active' => true,
            ],
            [
                'parent_id' => null,
                'name' => 'Professional Services',
                'description' => 'Professional and consulting services',
                'is_active' => true,
            ],
            [
                'parent_id' => null,
                'name' => 'Equipment & Maintenance',
                'description' => 'Equipment purchase and maintenance',
                'is_active' => true,
            ],
            [
                'parent_id' => null,
                'name' => 'Food & Entertainment',
                'description' => 'Food and entertainment expenses',
                'is_active' => true,
            ],
        ];

        // Create root categories first
        foreach ($categories as $category) {
            ExpenseCategory::create($category);
        }

        // Get created root categories for child relationships
        $officeExpenses = ExpenseCategory::where('name', 'Office Expenses')->first();
        $travel = ExpenseCategory::where('name', 'Travel & Transportation')->first();
        $utilities = ExpenseCategory::where('name', 'Utilities')->first();
        $marketing = ExpenseCategory::where('name', 'Marketing & Advertising')->first();
        $professional = ExpenseCategory::where('name', 'Professional Services')->first();
        $equipment = ExpenseCategory::where('name', 'Equipment & Maintenance')->first();
        $food = ExpenseCategory::where('name', 'Food & Entertainment')->first();

        // Child categories
        $childCategories = [
            // Office Expenses children
            [
                'parent_id' => $officeExpenses->id,
                'name' => 'Office Supplies',
                'description' => 'Stationery, paper, pens, etc.',
                'is_active' => true,
            ],
            [
                'parent_id' => $officeExpenses->id,
                'name' => 'Office Rent',
                'description' => 'Monthly office rental expenses',
                'is_active' => true,
            ],
            [
                'parent_id' => $officeExpenses->id,
                'name' => 'Cleaning Services',
                'description' => 'Office cleaning and maintenance',
                'is_active' => true,
            ],
            [
                'parent_id' => $officeExpenses->id,
                'name' => 'Security Services',
                'description' => 'Office security services',
                'is_active' => true,
            ],

            // Travel & Transportation children
            [
                'parent_id' => $travel->id,
                'name' => 'Air Travel',
                'description' => 'Flight tickets and air travel',
                'is_active' => true,
            ],
            [
                'parent_id' => $travel->id,
                'name' => 'Hotel Accommodation',
                'description' => 'Hotel stays during business trips',
                'is_active' => true,
            ],
            [
                'parent_id' => $travel->id,
                'name' => 'Local Transportation',
                'description' => 'Taxi, bus, train fares',
                'is_active' => true,
            ],
            [
                'parent_id' => $travel->id,
                'name' => 'Fuel Expenses',
                'description' => 'Vehicle fuel and maintenance',
                'is_active' => true,
            ],

            // Utilities children
            [
                'parent_id' => $utilities->id,
                'name' => 'Electricity',
                'description' => 'Electric power bills',
                'is_active' => true,
            ],
            [
                'parent_id' => $utilities->id,
                'name' => 'Water & Sewage',
                'description' => 'Water and sewage bills',
                'is_active' => true,
            ],
            [
                'parent_id' => $utilities->id,
                'name' => 'Internet & Phone',
                'description' => 'Internet and telephone services',
                'is_active' => true,
            ],
            [
                'parent_id' => $utilities->id,
                'name' => 'Gas',
                'description' => 'Gas utility bills',
                'is_active' => true,
            ],

            // Marketing & Advertising children
            [
                'parent_id' => $marketing->id,
                'name' => 'Digital Advertising',
                'description' => 'Online ads, social media marketing',
                'is_active' => true,
            ],
            [
                'parent_id' => $marketing->id,
                'name' => 'Print Advertising',
                'description' => 'Newspaper, magazine advertisements',
                'is_active' => true,
            ],
            [
                'parent_id' => $marketing->id,
                'name' => 'Events & Exhibitions',
                'description' => 'Trade shows and event participation',
                'is_active' => true,
            ],

            // Professional Services children
            [
                'parent_id' => $professional->id,
                'name' => 'Legal Services',
                'description' => 'Lawyer and legal consultation fees',
                'is_active' => true,
            ],
            [
                'parent_id' => $professional->id,
                'name' => 'Accounting Services',
                'description' => 'Accountant and audit fees',
                'is_active' => true,
            ],
            [
                'parent_id' => $professional->id,
                'name' => 'IT Consulting',
                'description' => 'IT support and consulting services',
                'is_active' => true,
            ],

            // Equipment & Maintenance children
            [
                'parent_id' => $equipment->id,
                'name' => 'Computer Equipment',
                'description' => 'Computers, laptops, servers',
                'is_active' => true,
            ],
            [
                'parent_id' => $equipment->id,
                'name' => 'Office Furniture',
                'description' => 'Desks, chairs, cabinets',
                'is_active' => true,
            ],
            [
                'parent_id' => $equipment->id,
                'name' => 'Equipment Repairs',
                'description' => 'Repair and maintenance of equipment',
                'is_active' => true,
            ],

            // Food & Entertainment children
            [
                'parent_id' => $food->id,
                'name' => 'Client Entertainment',
                'description' => 'Client dinners and entertainment',
                'is_active' => true,
            ],
            [
                'parent_id' => $food->id,
                'name' => 'Employee Meals',
                'description' => 'Staff lunch and catering',
                'is_active' => true,
            ],
            [
                'parent_id' => $food->id,
                'name' => 'Coffee & Refreshments',
                'description' => 'Office coffee and snacks',
                'is_active' => true,
            ],
        ];

        // Create child categories
        foreach ($childCategories as $category) {
            ExpenseCategory::create($category);
        }
    }
}