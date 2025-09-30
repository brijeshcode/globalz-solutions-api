<?php

namespace Database\Seeders;

use App\Models\Customers\Customer;
use App\Models\Setups\Customers\CustomerType;
use App\Models\Setups\Customers\CustomerGroup;
use App\Models\Setups\Customers\CustomerPaymentTerm;
use App\Models\Setups\Customers\CustomerProvince;
use App\Models\Setups\Customers\CustomerZone;
use App\Models\Employees\Employee;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class CustomerSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Creating 5 sample customers...');

        // Get existing setup data
        $customerTypes = CustomerType::where('is_active', true)->get();
        $customerGroups = CustomerGroup::where('is_active', true)->get();
        $paymentTerms = CustomerPaymentTerm::where('is_active', true)->get();
        $provinces = CustomerProvince::where('is_active', true)->get();
        $zones = CustomerZone::where('is_active', true)->get();

        // Get only employees from Sales department
        $salesEmployees = Employee::whereHas('department', function($query) {
            $query->where('name', 'Sales')->where('is_active', true);
        })->where('is_active', true)->get();

        $users = User::all();

        // Sample customer data
        $sampleCustomers = [
            [
                'name' => 'ABC Trading Corporation',
                'address' => '123 Business Street, Downtown District',
                'city' => 'New York',
                'telephone' => '+1-212-555-0101',
                'mobile' => '+1-212-555-0102',
                'email' => 'info@abctrading.com',
                'url' => 'https://www.abctrading.com',
                'contact_name' => 'John Anderson',
                // 'opening_balance' => 2500.00,
                'current_balance' => 2500.00,
                'discount_percentage' => 5.00,
                'credit_limit' => 5000.00,
                'gps_coordinates' => '40.7128,-74.0060',
                'mof_tax_number' => 'TX123456789',
                'notes' => 'Long-term customer with excellent payment history'
            ],
            [
                'name' => 'Global Electronics Ltd',
                'address' => '456 Technology Boulevard, Silicon Valley',
                'city' => 'San Francisco',
                'telephone' => '+1-415-555-0201',
                'mobile' => '+1-415-555-0202',
                'email' => 'sales@globalelectronics.com',
                'url' => 'https://www.globalelectronics.com',
                'contact_name' => 'Sarah Martinez',
                // 'opening_balance' => 1800.00,
                'current_balance' => 1800.00,
                'discount_percentage' => 7.50,
                'credit_limit' => 7500.00,
                'gps_coordinates' => '37.7749,-122.4194',
                'mof_tax_number' => 'TX987654321',
                'notes' => 'High-volume electronics retailer with bulk orders'
            ],
            [
                'name' => 'Metro Retail Solutions',
                'address' => '789 Commerce Avenue, Business District',
                'city' => 'Chicago',
                'telephone' => '+1-312-555-0301',
                'mobile' => '+1-312-555-0302',
                'email' => 'orders@metroretail.com',
                'url' => 'https://www.metroretail.com',
                'contact_name' => 'Michael Chen',
                // 'opening_balance' => 3200.00,
                'current_balance' => 3200.00,
                'discount_percentage' => 6.00,
                'credit_limit' => 8000.00,
                'gps_coordinates' => '41.8781,-87.6298',
                'mof_tax_number' => 'TX456789123',
                'notes' => 'Regional retail chain with multiple locations'
            ],
            [
                'name' => 'Pacific Systems Inc',
                'address' => '321 Innovation Drive, Tech Park',
                'city' => 'Seattle',
                'telephone' => '+1-206-555-0401',
                'mobile' => '+1-206-555-0402',
                'email' => 'contact@pacificsystems.com',
                'url' => 'https://www.pacificsystems.com',
                'contact_name' => 'Lisa Wang',
                // 'opening_balance' => 4100.00,
                'current_balance' => 4100.00,
                'discount_percentage' => 8.00,
                'credit_limit' => 10000.00,
                'gps_coordinates' => '47.6062,-122.3321',
                'mof_tax_number' => 'TX789123456',
                'notes' => 'Enterprise systems integrator with government contracts'
            ],
            [
                'name' => 'Sunshine Distributors',
                'address' => '654 Ocean Drive, Coastal Area',
                'city' => 'Miami',
                'telephone' => '+1-305-555-0501',
                'mobile' => '+1-305-555-0502',
                'email' => 'info@sunshinedist.com',
                'url' => 'https://www.sunshinedist.com',
                'contact_name' => 'Carlos Rodriguez',
                // 'opening_balance' => 2750.00,
                'current_balance' => 2750.00,
                'discount_percentage' => 5.50,
                'credit_limit' => 6000.00,
                'gps_coordinates' => '25.7617,-80.1918',
                'mof_tax_number' => 'TX321654987',
                'notes' => 'Caribbean and Latin America distribution specialist'
            ]
        ];

        DB::transaction(function () use ($sampleCustomers, $customerTypes, $customerGroups, $paymentTerms, $provinces, $zones, $salesEmployees, $users) {
            foreach ($sampleCustomers as $index => $customerData) {
                $customer = new Customer();

                $customer->fill($customerData);

                // Assign random related models if they exist
                if ($customerTypes->count() > 0) {
                    $customer->customer_type_id = $customerTypes->random()->id;
                }
                if ($customerGroups->count() > 0) {
                    $customer->customer_group_id = $customerGroups->random()->id;
                }
                if ($paymentTerms->count() > 0) {
                    $customer->customer_payment_term_id = $paymentTerms->random()->id;
                }
                if ($provinces->count() > 0) {
                    $customer->customer_province_id = $provinces->random()->id;
                }
                if ($zones->count() > 0) {
                    $customer->customer_zone_id = $zones->random()->id;
                }
                if ($salesEmployees->count() > 0) {
                    $customer->salesperson_id = $salesEmployees->random()->id;
                }

                // Set as active
                $customer->is_active = true;

                // Set created by user
                if ($users->count() > 0) {
                    $customer->created_by = $users->random()->id;
                }

                $customer->save();

                $this->command->info("Created customer: {$customer->code} - {$customer->name}");
            }
        });

        $this->command->info('Successfully created 5 sample customers with minimum credit limit of 5000!');
    }
}