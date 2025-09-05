<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $settings = [
            // Items Group
            [
                'group_name' => 'items',
                'key_name' => 'code_counter',
                'value' => '5000',
                'data_type' => Setting::TYPE_NUMBER,
                'description' => 'Next item code counter starting from 5000',
            ],
            [
                'group_name' => 'items',
                'key_name' => 'default_page_size',
                'value' => '15',
                'data_type' => Setting::TYPE_NUMBER,
                'description' => 'Default pagination size for items listing',
            ],
            [
                'group_name' => 'items',
                'key_name' => 'auto_generate_barcode',
                'value' => '0',
                'data_type' => Setting::TYPE_BOOLEAN,
                'description' => 'Auto-generate barcode for new items',
            ],
            [
                'group_name' => 'items',
                'key_name' => 'default_cost_calculation',
                'value' => 'weighted_average',
                'data_type' => Setting::TYPE_STRING,
                'description' => 'Default cost calculation method for new items',
            ],

            // Global System Settings
            [
                'group_name' => 'system',
                'key_name' => 'global_pagination',
                'value' => '25',
                'data_type' => Setting::TYPE_NUMBER,
                'description' => 'Default pagination size for all listings',
            ],
            [
                'group_name' => 'system',
                'key_name' => 'app_name',
                'value' => 'Globalz Solutions',
                'data_type' => Setting::TYPE_STRING,
                'description' => 'Application name displayed in UI',
            ],
            [
                'group_name' => 'system',
                'key_name' => 'timezone',
                'value' => 'Asia/Beirut',
                'data_type' => Setting::TYPE_STRING,
                'description' => 'Default application timezone',
            ],
            [
                'group_name' => 'system',
                'key_name' => 'date_format',
                'value' => 'd-m-Y',
                'data_type' => Setting::TYPE_STRING,
                'description' => 'Default date format for display',
            ],
            [
                'group_name' => 'system',
                'key_name' => 'default_currency',
                'value' => '1',
                'data_type' => Setting::TYPE_STRING,
                'description' => 'Default currency symbol',
            ],

            // Customers Group
            [
                'group_name' => 'customers',
                'key_name' => 'code_counter',
                'value' => config('app.customer_code_start', 50000000),
                'data_type' => Setting::TYPE_NUMBER,
                'description' => 'Next customer code counter starting from ' . config('app.customer_code_start', 50000000),
            ],
            [
                'group_name' => 'customers',
                'key_name' => 'default_page_size',
                'value' => '20',
                'data_type' => Setting::TYPE_NUMBER,
                'description' => 'Default pagination size for customers listing',
            ],

            // Suppliers Group  
            [
                'group_name' => 'suppliers',
                'key_name' => 'code_counter',
                'value' => '1000',
                'data_type' => Setting::TYPE_NUMBER,
                'description' => 'Next supplier code counter starting from 1000',
            ],
            [
                'group_name' => 'suppliers',
                'key_name' => 'default_page_size',
                'value' => '20',
                'data_type' => Setting::TYPE_NUMBER,
                'description' => 'Default pagination size for suppliers listing',
            ],
            [
                'group_name' => 'suppliers',
                'key_name' => 'default_payment_terms',
                'value' => '30',
                'data_type' => Setting::TYPE_NUMBER,
                'description' => 'Default payment terms in days for new suppliers',
            ],

            // Inventory Group
            [
                'group_name' => 'inventory',
                'key_name' => 'low_stock_alert_enabled',
                'value' => '1',
                'data_type' => Setting::TYPE_BOOLEAN,
                'description' => 'Enable low stock alerts',
            ],
            [
                'group_name' => 'inventory',
                'key_name' => 'low_stock_threshold_days',
                'value' => '7',
                'data_type' => Setting::TYPE_NUMBER,
                'description' => 'Days before item runs out to send alert',
            ],
            [
                'group_name' => 'inventory',
                'key_name' => 'enable_negative_stock',
                'value' => '0',
                'data_type' => Setting::TYPE_BOOLEAN,
                'description' => 'Allow negative stock quantities',
            ],

            // Financial Group
            [
                'group_name' => 'financial',
                'key_name' => 'decimal_places',
                'value' => '2',
                'data_type' => Setting::TYPE_NUMBER,
                'description' => 'Default decimal places for currency',
            ],
            [
                'group_name' => 'financial',
                'key_name' => 'tax_inclusive_pricing',
                'value' => '0',
                'data_type' => Setting::TYPE_BOOLEAN,
                'description' => 'Default tax inclusive pricing for items',
            ],
            [
                'group_name' => 'financial',
                'key_name' => 'default_tax_rate',
                'value' => '0.00',
                'data_type' => Setting::TYPE_NUMBER,
                'description' => 'Default tax rate percentage',
            ],

            // Security Group
            [
                'group_name' => 'security',
                'key_name' => 'session_timeout',
                'value' => '120',
                'data_type' => Setting::TYPE_NUMBER,
                'description' => 'Session timeout in minutes',
            ],
            [
                'group_name' => 'security',
                'key_name' => 'password_min_length',
                'value' => '8',
                'data_type' => Setting::TYPE_NUMBER,
                'description' => 'Minimum password length',
            ],
            [
                'group_name' => 'security',
                'key_name' => 'require_2fa',
                'value' => '0',
                'data_type' => Setting::TYPE_BOOLEAN,
                'description' => 'Require two-factor authentication',
            ],

            // Email Group
            [
                'group_name' => 'email',
                'key_name' => 'notifications_enabled',
                'value' => '1',
                'data_type' => Setting::TYPE_BOOLEAN,
                'description' => 'Enable email notifications',
            ],
            [
                'group_name' => 'email',
                'key_name' => 'admin_email',
                'value' => 'admin@globalz-solutions.com',
                'data_type' => Setting::TYPE_STRING,
                'description' => 'Administrator email address',
            ],
        ];

        foreach ($settings as $setting) {
            Setting::updateOrCreate(
                [
                    'group_name' => $setting['group_name'],
                    'key_name' => $setting['key_name']
                ],
                $setting
            );
        }

        $this->command->info('Settings seeded successfully!');
    }
}