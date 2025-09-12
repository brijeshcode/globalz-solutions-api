<?php

namespace Database\Seeders;

use App\Models\Items\Item;
use App\Models\Setups\ItemType;
use App\Models\Setups\ItemFamily;
use App\Models\Setups\ItemGroup;
use App\Models\Setups\ItemCategory;
use App\Models\Setups\ItemBrand;
use App\Models\Setups\ItemUnit;
use App\Models\Setups\Supplier;
use App\Models\Setups\TaxCode;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Creating 20 sample items...');

        // Get existing setup data
        $itemTypes = ItemType::active()->get();
        $itemFamilies = ItemFamily::active()->get();
        $itemGroups = ItemGroup::active()->get();
        $itemCategories = ItemCategory::active()->get();
        $itemBrands = ItemBrand::active()->get();
        $itemUnits = ItemUnit::active()->get();
        $suppliers = Supplier::active()->get();
        $taxCodes = TaxCode::active()->get();
        $users = User::all();

        // Sample item data
        $sampleItems = [
            [
                'short_name' => 'Laptop HP',
                'description' => 'HP EliteBook 840 G8 Business Laptop',
                'volume' => 2.5,
                'weight' => 1.36,
                'barcode' => '1234567890001',
                'base_cost' => 899.00,
                'base_sell' => 1299.00,
                'starting_price' => 1299.00,
                'starting_quantity' => 25.00,
                'low_quantity_alert' => 5.00,
                'cost_calculation' => 'weighted_average',
                'notes' => 'High-performance business laptop with Intel Core i7 processor'
            ],
            [
                'short_name' => 'Mouse Wireless',
                'description' => 'Logitech MX Master 3 Wireless Mouse',
                'volume' => 0.1,
                'weight' => 0.141,
                'barcode' => '1234567890002',
                'base_cost' => 45.00,
                'base_sell' => 89.99,
                'starting_price' => 89.99,
                'starting_quantity' => 150.00,
                'low_quantity_alert' => 20.00,
                'cost_calculation' => 'last_cost',
                'notes' => 'Ergonomic wireless mouse for productivity'
            ],
            [
                'short_name' => 'Monitor 27"',
                'description' => 'Dell UltraSharp 27" 4K Monitor',
                'volume' => 15.2,
                'weight' => 6.4,
                'barcode' => '1234567890003',
                'base_cost' => 350.00,
                'base_sell' => 549.99,
                'starting_price' => 549.99,
                'starting_quantity' => 40.00,
                'low_quantity_alert' => 8.00,
                'cost_calculation' => 'weighted_average',
                'notes' => '4K UHD display with excellent color accuracy'
            ],
            [
                'short_name' => 'Keyboard Mech',
                'description' => 'Corsair K95 RGB Mechanical Gaming Keyboard',
                'volume' => 0.8,
                'weight' => 1.25,
                'barcode' => '1234567890004',
                'base_cost' => 120.00,
                'base_sell' => 199.99,
                'starting_price' => 199.99,
                'starting_quantity' => 75.00,
                'low_quantity_alert' => 15.00,
                'cost_calculation' => 'last_cost',
                'notes' => 'Premium mechanical keyboard with RGB lighting'
            ],
            [
                'short_name' => 'Headphones BT',
                'description' => 'Sony WH-1000XM4 Bluetooth Headphones',
                'volume' => 0.3,
                'weight' => 0.254,
                'barcode' => '1234567890005',
                'base_cost' => 250.00,
                'base_sell' => 349.99,
                'starting_price' => 349.99,
                'starting_quantity' => 60.00,
                'low_quantity_alert' => 10.00,
                'cost_calculation' => 'weighted_average',
                'notes' => 'Noise-cancelling wireless headphones'
            ],
            [
                'short_name' => 'Tablet Pro',
                'description' => 'Apple iPad Pro 12.9" WiFi 256GB',
                'volume' => 0.6,
                'weight' => 0.682,
                'barcode' => '1234567890006',
                'base_cost' => 950.00,
                'base_sell' => 1299.99,
                'starting_price' => 1299.99,
                'starting_quantity' => 30.00,
                'low_quantity_alert' => 5.00,
                'cost_calculation' => 'last_cost',
                'notes' => 'Professional tablet with M1 chip'
            ],
            [
                'short_name' => 'Webcam 4K',
                'description' => 'Logitech Brio 4K Ultra HD Webcam',
                'volume' => 0.05,
                'weight' => 0.063,
                'barcode' => '1234567890007',
                'base_cost' => 150.00,
                'base_sell' => 219.99,
                'starting_price' => 219.99,
                'starting_quantity' => 85.00,
                'low_quantity_alert' => 20.00,
                'cost_calculation' => 'weighted_average',
                'notes' => '4K streaming webcam with auto-focus'
            ],
            [
                'short_name' => 'SSD 1TB',
                'description' => 'Samsung 980 PRO 1TB NVMe SSD',
                'volume' => 0.02,
                'weight' => 0.007,
                'barcode' => '1234567890008',
                'base_cost' => 80.00,
                'base_sell' => 149.99,
                'starting_price' => 149.99,
                'starting_quantity' => 200.00,
                'low_quantity_alert' => 25.00,
                'cost_calculation' => 'last_cost',
                'notes' => 'High-speed NVMe SSD for gaming and professional use'
            ],
            [
                'short_name' => 'Router WiFi6',
                'description' => 'ASUS AX6000 WiFi 6 Gaming Router',
                'volume' => 1.2,
                'weight' => 1.05,
                'barcode' => '1234567890009',
                'base_cost' => 200.00,
                'base_sell' => 349.99,
                'starting_price' => 349.99,
                'starting_quantity' => 45.00,
                'low_quantity_alert' => 8.00,
                'cost_calculation' => 'weighted_average',
                'notes' => 'Next-gen WiFi 6 router with advanced gaming features'
            ],
            [
                'short_name' => 'Printer All-in-One',
                'description' => 'HP OfficeJet Pro 9015e All-in-One Printer',
                'volume' => 8.5,
                'weight' => 9.8,
                'barcode' => '1234567890010',
                'base_cost' => 180.00,
                'base_sell' => 279.99,
                'starting_price' => 279.99,
                'starting_quantity' => 35.00,
                'low_quantity_alert' => 7.00,
                'cost_calculation' => 'last_cost',
                'notes' => 'Color inkjet all-in-one with wireless connectivity'
            ],
            [
                'short_name' => 'Phone Galaxy',
                'description' => 'Samsung Galaxy S23 Ultra 256GB',
                'volume' => 0.12,
                'weight' => 0.234,
                'barcode' => '1234567890011',
                'base_cost' => 850.00,
                'base_sell' => 1199.99,
                'starting_price' => 1199.99,
                'starting_quantity' => 50.00,
                'low_quantity_alert' => 10.00,
                'cost_calculation' => 'weighted_average',
                'notes' => 'Flagship Android smartphone with S Pen'
            ],
            [
                'short_name' => 'Charger USB-C',
                'description' => 'Anker 65W GaN USB-C Fast Charger',
                'volume' => 0.08,
                'weight' => 0.112,
                'barcode' => '1234567890012',
                'base_cost' => 25.00,
                'base_sell' => 49.99,
                'starting_price' => 49.99,
                'starting_quantity' => 300.00,
                'low_quantity_alert' => 50.00,
                'cost_calculation' => 'last_cost',
                'notes' => 'Compact high-speed USB-C charger'
            ],
            [
                'short_name' => 'Speakers BT',
                'description' => 'JBL Charge 5 Portable Bluetooth Speaker',
                'volume' => 1.1,
                'weight' => 0.96,
                'barcode' => '1234567890013',
                'base_cost' => 120.00,
                'base_sell' => 179.99,
                'starting_price' => 179.99,
                'starting_quantity' => 80.00,
                'low_quantity_alert' => 15.00,
                'cost_calculation' => 'weighted_average',
                'notes' => 'Waterproof portable speaker with power bank function'
            ],
            [
                'short_name' => 'Cable HDMI',
                'description' => 'Ultra High Speed HDMI Cable 6ft',
                'volume' => 0.15,
                'weight' => 0.18,
                'barcode' => '1234567890014',
                'base_cost' => 8.00,
                'base_sell' => 19.99,
                'starting_price' => 19.99,
                'starting_quantity' => 500.00,
                'low_quantity_alert' => 100.00,
                'cost_calculation' => 'last_cost',
                'notes' => 'Premium HDMI 2.1 cable supporting 8K video'
            ],
            [
                'short_name' => 'Camera Action',
                'description' => 'GoPro HERO11 Black Action Camera',
                'volume' => 0.08,
                'weight' => 0.153,
                'barcode' => '1234567890015',
                'base_cost' => 320.00,
                'base_sell' => 499.99,
                'starting_price' => 499.99,
                'starting_quantity' => 40.00,
                'low_quantity_alert' => 8.00,
                'cost_calculation' => 'weighted_average',
                'notes' => 'Rugged 5.3K action camera with image stabilization'
            ],
            [
                'short_name' => 'Dock USB-C',
                'description' => 'CalDigit TS3 Plus Thunderbolt 3 Dock',
                'volume' => 0.5,
                'weight' => 0.54,
                'barcode' => '1234567890016',
                'base_cost' => 180.00,
                'base_sell' => 279.99,
                'starting_price' => 279.99,
                'starting_quantity' => 55.00,
                'low_quantity_alert' => 10.00,
                'cost_calculation' => 'last_cost',
                'notes' => 'Professional Thunderbolt 3 docking station'
            ],
            [
                'short_name' => 'Drive External',
                'description' => 'WD My Passport 2TB Portable HDD',
                'volume' => 0.12,
                'weight' => 0.23,
                'barcode' => '1234567890017',
                'base_cost' => 55.00,
                'base_sell' => 89.99,
                'starting_price' => 89.99,
                'starting_quantity' => 120.00,
                'low_quantity_alert' => 25.00,
                'cost_calculation' => 'weighted_average',
                'notes' => 'Portable external hard drive with USB 3.0'
            ],
            [
                'short_name' => 'Stand Laptop',
                'description' => 'Rain Design mStand Laptop Stand',
                'volume' => 0.8,
                'weight' => 1.36,
                'barcode' => '1234567890018',
                'base_cost' => 35.00,
                'base_sell' => 59.99,
                'starting_price' => 59.99,
                'starting_quantity' => 90.00,
                'low_quantity_alert' => 18.00,
                'cost_calculation' => 'last_cost',
                'notes' => 'Aluminum laptop stand with ergonomic design'
            ],
            [
                'short_name' => 'Light Ring',
                'description' => 'Neewer 18" LED Ring Light with Stand',
                'volume' => 2.8,
                'weight' => 2.5,
                'barcode' => '1234567890019',
                'base_cost' => 60.00,
                'base_sell' => 99.99,
                'starting_price' => 99.99,
                'starting_quantity' => 70.00,
                'low_quantity_alert' => 12.00,
                'cost_calculation' => 'weighted_average',
                'notes' => 'Professional ring light for content creation'
            ],
            [
                'short_name' => 'Power Bank',
                'description' => 'Anker PowerCore 10000 Portable Charger',
                'volume' => 0.15,
                'weight' => 0.18,
                'barcode' => '1234567890020',
                'base_cost' => 20.00,
                'base_sell' => 39.99,
                'starting_price' => 39.99,
                'starting_quantity' => 250.00,
                'low_quantity_alert' => 40.00,
                'cost_calculation' => 'last_cost',
                'notes' => 'Compact 10000mAh power bank with fast charging'
            ]
        ];

        DB::transaction(function () use ($sampleItems, $itemTypes, $itemFamilies, $itemGroups, $itemCategories, $itemBrands, $itemUnits, $suppliers, $taxCodes, $users) {
            foreach ($sampleItems as $index => $itemData) {
                $item = new Item();
                
                // Don't set code - let the model auto-generate it
                $item->fill($itemData);
                
                // Assign random related models
                if ($itemTypes->count() > 0) {
                    $item->item_type_id = $itemTypes->random()->id;
                }
                if ($itemFamilies->count() > 0) {
                    $item->item_family_id = $itemFamilies->random()->id;
                }
                if ($itemGroups->count() > 0) {
                    $item->item_group_id = $itemGroups->random()->id;
                }
                if ($itemCategories->count() > 0) {
                    $item->item_category_id = $itemCategories->random()->id;
                }
                if ($itemBrands->count() > 0) {
                    $item->item_brand_id = $itemBrands->random()->id;
                }
                if ($itemUnits->count() > 0) {
                    $item->item_unit_id = $itemUnits->random()->id;
                }
                if ($suppliers->count() > 0) {
                    $item->supplier_id = $suppliers->random()->id;
                }
                if ($taxCodes->count() > 0) {
                    $item->tax_code_id = $taxCodes->random()->id;
                }
                
                // Set as active (random mix)
                $item->is_active = $index < 18; // First 18 active, last 2 inactive
                
                // Set created by user
                if ($users->count() > 0) {
                    $item->created_by = $users->random()->id;
                }
                
                $item->save();
                \App\Services\Inventory\InventoryService::set(
                    $item->id,
                    1,
                    (int) $itemData['starting_quantity'],
                    'Initial inventory from item creation'
                );
                
                $this->command->info("Created item: {$item->code} - {$item->description}");
            }
        });

        $this->command->info('Successfully created 20 sample items!');
    }
}