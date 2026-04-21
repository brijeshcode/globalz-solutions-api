<?php

use App\Models\Customers\Customer;
use Illuminate\Http\UploadedFile;
use Tests\Feature\Customers\Customers\Concerns\HasCustomerSetup;

uses(HasCustomerSetup::class);

beforeEach(function () {
    $this->setUpCustomers();
});

function makeCsv(array $headers, array $rows): UploadedFile
{
    $handle = fopen('php://temp', 'w+');
    fputcsv($handle, $headers);
    foreach ($rows as $row) {
        fputcsv($handle, $row);
    }
    rewind($handle);
    $content = stream_get_contents($handle);
    fclose($handle);

    return UploadedFile::fake()->createWithContent('customers.csv', $content);
}

function importHeaders(array $extra = []): array
{
    return array_merge(
        ['name', 'customer_type', 'city', 'mobile', 'salesperson', 'price_list_inv_code', 'price_list_inx_code'],
        $extra
    );
}

// --- New customer ---

it('imports a new customer from csv', function () {
    $file = makeCsv(importHeaders(), [[
        'Imported Customer',
        $this->customerType->name,
        'Beirut',
        '0501234567',
        $this->salesperson->code,
        $this->priceListINV->code,
        $this->priceListINX->code,
    ]]);

    $this->postJson(route('customers.import'), ['file' => $file])
        ->assertCreated()
        ->assertJsonPath('data.imported', 1)
        ->assertJsonPath('data.updated', 0);

    $this->assertDatabaseHas('customers', ['name' => 'Imported Customer', 'city' => 'Beirut']);
});

// --- Update existing ---

it('updates existing customer when update_existing is true', function () {
    $customer = Customer::factory()->create([
        'code'              => '99000001',
        'name'              => 'Old Name',
        'city'              => 'Old City',
        'mobile'            => '0500000001',
        'customer_type_id'  => $this->customerType->id,
        'salesperson_id'    => $this->salesperson->id,
        'price_list_id_INV' => $this->priceListINV->id,
        'price_list_id_INX' => $this->priceListINX->id,
    ]);

    $file = makeCsv(importHeaders(['code']), [[
        'New Name',
        $this->customerType->name,
        'New City',
        '0500000001',
        $this->salesperson->code,
        $this->priceListINV->code,
        $this->priceListINX->code,
        $customer->code,
    ]]);

    $this->postJson(route('customers.import'), ['file' => $file, 'update_existing' => true])
        ->assertCreated()
        ->assertJsonPath('data.updated', 1);

    $this->assertDatabaseHas('customers', ['id' => $customer->id, 'name' => 'New Name', 'city' => 'New City']);
});

it('skips duplicate when update_existing is false', function () {
    $customer = Customer::factory()->create([
        'code'              => '99000002',
        'customer_type_id'  => $this->customerType->id,
        'salesperson_id'    => $this->salesperson->id,
        'price_list_id_INV' => $this->priceListINV->id,
        'price_list_id_INX' => $this->priceListINX->id,
    ]);

    $file = makeCsv(importHeaders(['code']), [[
        'Some Customer',
        $this->customerType->name,
        'Beirut',
        '0500000002',
        $this->salesperson->code,
        $this->priceListINV->code,
        $this->priceListINX->code,
        $customer->code,
    ]]);

    $this->postJson(route('customers.import'), ['file' => $file, 'update_existing' => false])
        ->assertStatus(422)
        ->assertJsonPath('data.skipped', 1);
});

// --- Bug-fix: absent optional columns must not overwrite existing data on update ---

it('preserves total_old_sales when column is absent from file', function () {
    $customer = Customer::factory()->create([
        'code'              => '99000010',
        'city'              => 'Beirut',
        'mobile'            => '0500000010',
        'customer_type_id'  => $this->customerType->id,
        'salesperson_id'    => $this->salesperson->id,
        'price_list_id_INV' => $this->priceListINV->id,
        'price_list_id_INX' => $this->priceListINX->id,
        'total_old_sales'   => 15000.00,
    ]);

    $file = makeCsv(importHeaders(['code']), [[
        'Test Customer',
        $this->customerType->name,
        'Beirut',
        '0500000010',
        $this->salesperson->code,
        $this->priceListINV->code,
        $this->priceListINX->code,
        $customer->code,
    ]]);

    $this->postJson(route('customers.import'), ['file' => $file, 'update_existing' => true])
        ->assertCreated()
        ->assertJsonPath('data.updated', 1);

    expect((float) $customer->fresh()->total_old_sales)->toBe(15000.0);
});

it('preserves is_active when column is absent from file', function () {
    $customer = Customer::factory()->create([
        'code'              => '99000011',
        'city'              => 'Beirut',
        'mobile'            => '0500000011',
        'customer_type_id'  => $this->customerType->id,
        'salesperson_id'    => $this->salesperson->id,
        'price_list_id_INV' => $this->priceListINV->id,
        'price_list_id_INX' => $this->priceListINX->id,
        'is_active'         => false,
    ]);

    $file = makeCsv(importHeaders(['code']), [[
        'Test Customer',
        $this->customerType->name,
        'Beirut',
        '0500000011',
        $this->salesperson->code,
        $this->priceListINV->code,
        $this->priceListINX->code,
        $customer->code,
    ]]);

    $this->postJson(route('customers.import'), ['file' => $file, 'update_existing' => true])
        ->assertCreated()
        ->assertJsonPath('data.updated', 1);

    expect($customer->fresh()->is_active)->toBeFalse();
});

it('preserves discount_percentage when column is absent from file', function () {
    $customer = Customer::factory()->create([
        'code'                => '99000012',
        'city'                => 'Beirut',
        'mobile'              => '0500000012',
        'customer_type_id'    => $this->customerType->id,
        'salesperson_id'      => $this->salesperson->id,
        'price_list_id_INV'   => $this->priceListINV->id,
        'price_list_id_INX'   => $this->priceListINX->id,
        'discount_percentage' => 12.50,
    ]);

    $file = makeCsv(importHeaders(['code']), [[
        'Test Customer',
        $this->customerType->name,
        'Beirut',
        '0500000012',
        $this->salesperson->code,
        $this->priceListINV->code,
        $this->priceListINX->code,
        $customer->code,
    ]]);

    $this->postJson(route('customers.import'), ['file' => $file, 'update_existing' => true])
        ->assertCreated()
        ->assertJsonPath('data.updated', 1);

    expect((float) $customer->fresh()->discount_percentage)->toBe(12.50);
});

it('preserves current_balance on update', function () {
    $customer = Customer::factory()->create([
        'code'              => '99000013',
        'city'              => 'Beirut',
        'mobile'            => '0500000013',
        'customer_type_id'  => $this->customerType->id,
        'salesperson_id'    => $this->salesperson->id,
        'price_list_id_INV' => $this->priceListINV->id,
        'price_list_id_INX' => $this->priceListINX->id,
        'current_balance'   => 5000.00,
    ]);

    $file = makeCsv(importHeaders(['code']), [[
        'Test Customer',
        $this->customerType->name,
        'Beirut',
        '0500000013',
        $this->salesperson->code,
        $this->priceListINV->code,
        $this->priceListINX->code,
        $customer->code,
    ]]);

    $this->postJson(route('customers.import'), ['file' => $file, 'update_existing' => true])
        ->assertCreated()
        ->assertJsonPath('data.updated', 1);

    expect((float) $customer->fresh()->current_balance)->toBe(5000.0);
});

// --- Validation ---

it('rejects import when no file is provided', function () {
    $this->postJson(route('customers.import'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['file']);
});

it('returns error for row with missing required name field', function () {
    $file = makeCsv(importHeaders(), [[
        '',
        $this->customerType->name,
        'Beirut',
        '0501234567',
        $this->salesperson->code,
        $this->priceListINV->code,
        $this->priceListINX->code,
    ]]);

    $this->postJson(route('customers.import'), ['file' => $file])
        ->assertStatus(422)
        ->assertJsonPath('data.errors.0.error', 'Customer name is required');
});
