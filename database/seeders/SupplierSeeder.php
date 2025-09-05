<?php

namespace Database\Seeders;

use App\Models\Setups\Supplier;
use App\Models\Setups\SupplierType;
use App\Models\Setups\SupplierPaymentTerm;
use App\Models\Setups\Country;
use App\Models\Setups\Generals\Currencies\Currency;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SupplierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Creating 20 sample suppliers...');

        // Get existing setup data
        $supplierTypes = SupplierType::active()->get();
        $paymentTerms = SupplierPaymentTerm::active()->get();
        $countries = Country::active()->get();
        $currencies = Currency::active()->get();
        $users = User::all();

        // Sample supplier data
        $sampleSuppliers = [
            [
                'name' => 'TechFlow Solutions Inc',
                'address' => '1234 Technology Blvd, San Francisco, CA 94107',
                'phone' => '+1-415-555-0123',
                'mobile' => '+1-415-555-0124',
                'email' => 'info@techflow.com',
                'url' => 'https://www.techflow.com',
                'contact_person' => 'John Smith',
                'contact_person_email' => 'john.smith@techflow.com',
                'contact_person_mobile' => '+1-415-555-0125',
                'opening_balance' => 15000.00,
                'discount_percentage' => 5.00,
                'ship_from' => 'San Francisco Warehouse',
                'bank_info' => 'Bank of America - Account: 123456789',
                'notes' => 'Leading supplier of computer hardware and electronics'
            ],
            [
                'name' => 'Global Electronics Ltd',
                'address' => '567 Commerce Street, New York, NY 10013',
                'phone' => '+1-212-555-0234',
                'mobile' => '+1-212-555-0235',
                'email' => 'sales@globalelectronics.com',
                'url' => 'https://www.globalelectronics.com',
                'contact_person' => 'Sarah Johnson',
                'contact_person_email' => 'sarah.johnson@globalelectronics.com',
                'contact_person_mobile' => '+1-212-555-0236',
                'opening_balance' => 25000.00,
                'discount_percentage' => 7.50,
                'ship_from' => 'New York Distribution Center',
                'bank_info' => 'Chase Bank - Account: 987654321',
                'notes' => 'Wholesale electronics distributor with global reach'
            ],
            [
                'name' => 'Digital Components Corp',
                'address' => '890 Industrial Ave, Austin, TX 78701',
                'phone' => '+1-512-555-0345',
                'mobile' => '+1-512-555-0346',
                'email' => 'orders@digitalcomponents.com',
                'url' => 'https://www.digitalcomponents.com',
                'contact_person' => 'Mike Chen',
                'contact_person_email' => 'mike.chen@digitalcomponents.com',
                'contact_person_mobile' => '+1-512-555-0347',
                'opening_balance' => 18500.00,
                'discount_percentage' => 6.00,
                'ship_from' => 'Austin Facility',
                'bank_info' => 'Wells Fargo - Account: 456789123',
                'notes' => 'Specialized in computer peripherals and accessories'
            ],
            [
                'name' => 'Advanced Tech Systems',
                'address' => '321 Innovation Drive, Seattle, WA 98101',
                'phone' => '+1-206-555-0456',
                'mobile' => '+1-206-555-0457',
                'email' => 'contact@advancedtech.com',
                'url' => 'https://www.advancedtech.com',
                'contact_person' => 'Lisa Wang',
                'contact_person_email' => 'lisa.wang@advancedtech.com',
                'contact_person_mobile' => '+1-206-555-0458',
                'opening_balance' => 32000.00,
                'discount_percentage' => 8.00,
                'ship_from' => 'Seattle Distribution Hub',
                'bank_info' => 'US Bank - Account: 789123456',
                'notes' => 'Premium supplier of enterprise technology solutions'
            ],
            [
                'name' => 'Metro Electronics Supply',
                'address' => '654 Business Park, Chicago, IL 60601',
                'phone' => '+1-312-555-0567',
                'mobile' => '+1-312-555-0568',
                'email' => 'info@metroelectronics.com',
                'url' => 'https://www.metroelectronics.com',
                'contact_person' => 'David Brown',
                'contact_person_email' => 'david.brown@metroelectronics.com',
                'contact_person_mobile' => '+1-312-555-0569',
                'opening_balance' => 12500.00,
                'discount_percentage' => 4.50,
                'ship_from' => 'Chicago Warehouse',
                'bank_info' => 'PNC Bank - Account: 321654987',
                'notes' => 'Regional electronics supplier serving Midwest'
            ],
            [
                'name' => 'Pacific Hardware Solutions',
                'address' => '987 Coast Highway, Los Angeles, CA 90210',
                'phone' => '+1-323-555-0678',
                'mobile' => '+1-323-555-0679',
                'email' => 'sales@pacifichardware.com',
                'url' => 'https://www.pacifichardware.com',
                'contact_person' => 'Jennifer Martinez',
                'contact_person_email' => 'jennifer.martinez@pacifichardware.com',
                'contact_person_mobile' => '+1-323-555-0680',
                'opening_balance' => 28000.00,
                'discount_percentage' => 6.75,
                'ship_from' => 'Los Angeles Port Facility',
                'bank_info' => 'Bank of the West - Account: 654987321',
                'notes' => 'Import/export specialist for Asian tech products'
            ],
            [
                'name' => 'Northeast Tech Partners',
                'address' => '147 Tech Plaza, Boston, MA 02101',
                'phone' => '+1-617-555-0789',
                'mobile' => '+1-617-555-0790',
                'email' => 'orders@netech.com',
                'url' => 'https://www.netech.com',
                'contact_person' => 'Robert Kim',
                'contact_person_email' => 'robert.kim@netech.com',
                'contact_person_mobile' => '+1-617-555-0791',
                'opening_balance' => 21000.00,
                'discount_percentage' => 5.25,
                'ship_from' => 'Boston Tech Center',
                'bank_info' => 'TD Bank - Account: 987321654',
                'notes' => 'Technology partner for educational and corporate clients'
            ],
            [
                'name' => 'Southern Electronics Hub',
                'address' => '258 Commerce Blvd, Atlanta, GA 30301',
                'phone' => '+1-404-555-0890',
                'mobile' => '+1-404-555-0891',
                'email' => 'info@southernelectronics.com',
                'url' => 'https://www.southernelectronics.com',
                'contact_person' => 'Amanda Taylor',
                'contact_person_email' => 'amanda.taylor@southernelectronics.com',
                'contact_person_mobile' => '+1-404-555-0892',
                'opening_balance' => 16500.00,
                'discount_percentage' => 4.75,
                'ship_from' => 'Atlanta Distribution',
                'bank_info' => 'SunTrust Bank - Account: 147258369',
                'notes' => 'Full-service electronics distributor for Southeast region'
            ],
            [
                'name' => 'Mountain Tech Supplies',
                'address' => '369 Mountain View Dr, Denver, CO 80201',
                'phone' => '+1-303-555-0901',
                'mobile' => '+1-303-555-0902',
                'email' => 'sales@mountaintech.com',
                'url' => 'https://www.mountaintech.com',
                'contact_person' => 'Steve Wilson',
                'contact_person_email' => 'steve.wilson@mountaintech.com',
                'contact_person_mobile' => '+1-303-555-0903',
                'opening_balance' => 13750.00,
                'discount_percentage' => 5.50,
                'ship_from' => 'Denver Central Warehouse',
                'bank_info' => 'First Bank - Account: 258369147',
                'notes' => 'Reliable supplier for Rocky Mountain region businesses'
            ],
            [
                'name' => 'Coastal Computer Systems',
                'address' => '741 Ocean Drive, Miami, FL 33101',
                'phone' => '+1-305-555-1012',
                'mobile' => '+1-305-555-1013',
                'email' => 'contact@coastalcomputers.com',
                'url' => 'https://www.coastalcomputers.com',
                'contact_person' => 'Carlos Rodriguez',
                'contact_person_email' => 'carlos.rodriguez@coastalcomputers.com',
                'contact_person_mobile' => '+1-305-555-1014',
                'opening_balance' => 19250.00,
                'discount_percentage' => 6.25,
                'ship_from' => 'Miami Port Terminal',
                'bank_info' => 'Regions Bank - Account: 369147258',
                'notes' => 'Caribbean and Latin America technology distributor'
            ],
            [
                'name' => 'Central Valley Electronics',
                'address' => '852 Valley Road, Phoenix, AZ 85001',
                'phone' => '+1-602-555-1123',
                'mobile' => '+1-602-555-1124',
                'email' => 'orders@centralvalley.com',
                'url' => 'https://www.centralvalley.com',
                'contact_person' => 'Maria Garcia',
                'contact_person_email' => 'maria.garcia@centralvalley.com',
                'contact_person_mobile' => '+1-602-555-1125',
                'opening_balance' => 14200.00,
                'discount_percentage' => 4.25,
                'ship_from' => 'Phoenix Distribution',
                'bank_info' => 'Desert Financial - Account: 741852963',
                'notes' => 'Southwest regional electronics and computer supplier'
            ],
            [
                'name' => 'Great Lakes Tech Corp',
                'address' => '963 Lakeshore Blvd, Detroit, MI 48201',
                'phone' => '+1-313-555-1234',
                'mobile' => '+1-313-555-1235',
                'email' => 'info@greatlakestech.com',
                'url' => 'https://www.greatlakestech.com',
                'contact_person' => 'Kevin O\'Brien',
                'contact_person_email' => 'kevin.obrien@greatlakestech.com',
                'contact_person_mobile' => '+1-313-555-1236',
                'opening_balance' => 22750.00,
                'discount_percentage' => 7.25,
                'ship_from' => 'Detroit Industrial Complex',
                'bank_info' => 'Comerica Bank - Account: 852963741',
                'notes' => 'Industrial and automotive electronics specialist'
            ],
            [
                'name' => 'Plains Digital Supply',
                'address' => '159 Prairie Ave, Kansas City, MO 64101',
                'phone' => '+1-816-555-1345',
                'mobile' => '+1-816-555-1346',
                'email' => 'sales@plainsdigital.com',
                'url' => 'https://www.plainsdigital.com',
                'contact_person' => 'Rachel Thompson',
                'contact_person_email' => 'rachel.thompson@plainsdigital.com',
                'contact_person_mobile' => '+1-816-555-1347',
                'opening_balance' => 17800.00,
                'discount_percentage' => 5.75,
                'ship_from' => 'Kansas City Logistics Center',
                'bank_info' => 'Commerce Bank - Account: 963741852',
                'notes' => 'Central Plains technology distribution hub'
            ],
            [
                'name' => 'Cascade Electronics Inc',
                'address' => '753 Forest Lane, Portland, OR 97201',
                'phone' => '+1-503-555-1456',
                'mobile' => '+1-503-555-1457',
                'email' => 'contact@cascadeelectronics.com',
                'url' => 'https://www.cascadeelectronics.com',
                'contact_person' => 'Emily Davis',
                'contact_person_email' => 'emily.davis@cascadeelectronics.com',
                'contact_person_mobile' => '+1-503-555-1458',
                'opening_balance' => 26500.00,
                'discount_percentage' => 6.50,
                'ship_from' => 'Portland Green Facility',
                'bank_info' => 'Key Bank - Account: 159753486',
                'notes' => 'Eco-friendly electronics supplier for Pacific Northwest'
            ],
            [
                'name' => 'Lone Star Components',
                'address' => '486 Oil Field Rd, Houston, TX 77001',
                'phone' => '+1-713-555-1567',
                'mobile' => '+1-713-555-1568',
                'email' => 'orders@lonestarcomponents.com',
                'url' => 'https://www.lonestarcomponents.com',
                'contact_person' => 'James Anderson',
                'contact_person_email' => 'james.anderson@lonestarcomponents.com',
                'contact_person_mobile' => '+1-713-555-1569',
                'opening_balance' => 30500.00,
                'discount_percentage' => 8.25,
                'ship_from' => 'Houston Energy Corridor',
                'bank_info' => 'JP Morgan Chase - Account: 753486159',
                'notes' => 'Industrial electronics for energy and petrochemical sectors'
            ],
            [
                'name' => 'Garden State Electronics',
                'address' => '642 Highway 1, Newark, NJ 07101',
                'phone' => '+1-973-555-1678',
                'mobile' => '+1-973-555-1679',
                'email' => 'info@gardenstateelectronics.com',
                'url' => 'https://www.gardenstateelectronics.com',
                'contact_person' => 'Nicole Lee',
                'contact_person_email' => 'nicole.lee@gardenstateelectronics.com',
                'contact_person_mobile' => '+1-973-555-1680',
                'opening_balance' => 20100.00,
                'discount_percentage' => 5.00,
                'ship_from' => 'Newark Port Complex',
                'bank_info' => 'Bank of New York - Account: 486159753',
                'notes' => 'Northeast corridor electronics distribution specialist'
            ],
            [
                'name' => 'Rocky Mountain Digital',
                'address' => '317 Ski Lodge Way, Salt Lake City, UT 84101',
                'phone' => '+1-801-555-1789',
                'mobile' => '+1-801-555-1790',
                'email' => 'sales@rockymountaindigital.com',
                'url' => 'https://www.rockymountaindigital.com',
                'contact_person' => 'Tyler Mitchell',
                'contact_person_email' => 'tyler.mitchell@rockymountaindigital.com',
                'contact_person_mobile' => '+1-801-555-1791',
                'opening_balance' => 15900.00,
                'discount_percentage' => 4.00,
                'ship_from' => 'Salt Lake Distribution',
                'bank_info' => 'Zions Bank - Account: 642317864',
                'notes' => 'Mountain West technology and digital solutions provider'
            ],
            [
                'name' => 'Bluegrass Tech Solutions',
                'address' => '428 Derby Lane, Louisville, KY 40201',
                'phone' => '+1-502-555-1890',
                'mobile' => '+1-502-555-1891',
                'email' => 'contact@bluegrasstech.com',
                'url' => 'https://www.bluegrasstech.com',
                'contact_person' => 'Hannah Clark',
                'contact_person_email' => 'hannah.clark@bluegrasstech.com',
                'contact_person_mobile' => '+1-502-555-1892',
                'opening_balance' => 11800.00,
                'discount_percentage' => 3.75,
                'ship_from' => 'Louisville Logistics Park',
                'bank_info' => 'Fifth Third Bank - Account: 317864428',
                'notes' => 'Regional technology supplier for Ohio Valley region'
            ],
            [
                'name' => 'Desert Tech Distributors',
                'address' => '864 Cactus Drive, Las Vegas, NV 89101',
                'phone' => '+1-702-555-1901',
                'mobile' => '+1-702-555-1902',
                'email' => 'orders@deserttech.com',
                'url' => 'https://www.deserttech.com',
                'contact_person' => 'Brandon White',
                'contact_person_email' => 'brandon.white@deserttech.com',
                'contact_person_mobile' => '+1-702-555-1903',
                'opening_balance' => 24600.00,
                'discount_percentage' => 7.00,
                'ship_from' => 'Las Vegas Tech Center',
                'bank_info' => 'Nevada State Bank - Account: 864428317',
                'notes' => 'Gaming and entertainment technology specialist'
            ],
            [
                'name' => 'Empire Electronics Supply',
                'address' => '579 Broadway, Albany, NY 12201',
                'phone' => '+1-518-555-2012',
                'mobile' => '+1-518-555-2013',
                'email' => 'info@empireelectronics.com',
                'url' => 'https://www.empireelectronics.com',
                'contact_person' => 'Samantha Green',
                'contact_person_email' => 'samantha.green@empireelectronics.com',
                'contact_person_mobile' => '+1-518-555-2014',
                'opening_balance' => 18400.00,
                'discount_percentage' => 5.50,
                'ship_from' => 'Albany Distribution Hub',
                'bank_info' => 'KeyBank - Account: 579123684',
                'notes' => 'Upstate New York electronics and technology distributor'
            ]
        ];

        DB::transaction(function () use ($sampleSuppliers, $supplierTypes, $paymentTerms, $countries, $currencies, $users) {
            $code = 5000;
            foreach ($sampleSuppliers as $index => $supplierData) {
                $supplier = new Supplier();
                
                $supplier->fill($supplierData);
                $supplier->code = $code++;
                
                // Assign random related models
                if ($supplierTypes->count() > 0) {
                    $supplier->supplier_type_id = $supplierTypes->random()->id;
                }
                if ($paymentTerms->count() > 0) {
                    $supplier->payment_term_id = $paymentTerms->random()->id;
                }
                if ($countries->count() > 0) {
                    $supplier->country_id = $countries->random()->id;
                }
                if ($currencies->count() > 0) {
                    $supplier->currency_id = $currencies->random()->id;
                }
                
                // Set as active (random mix)
                $supplier->is_active = $index < 18; // First 18 active, last 2 inactive
                
                // Set created by user
                if ($users->count() > 0) {
                    $supplier->created_by = $users->random()->id;
                }
                
                $supplier->save();
                
                $this->command->info("Created supplier: {$supplier->code} - {$supplier->name}");
            }
        });

        $this->command->info('Successfully created 20 sample suppliers!');
    }
}