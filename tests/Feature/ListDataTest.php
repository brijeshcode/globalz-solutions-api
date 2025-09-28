<?php

use App\Models\Customers\Customer;
use App\Models\Setups\Warehouse;
use App\Models\Setups\Generals\Currencies\Currency;
use App\Models\Setups\Supplier;
use App\Models\Setups\Customers\CustomerPaymentTerm;
use App\Models\Setups\ItemBrand;
use App\Models\Setups\TaxCode;
use App\Models\User;

uses()->group('list-data');

beforeEach(function () {
    $this->refreshDatabase();
    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'sanctum');
});

it('can get warehouses list', function () {
    Warehouse::factory()->create(['is_active' => true, 'name' => 'Main Warehouse']);
    Warehouse::factory()->create(['is_active' => false, 'name' => 'Inactive Warehouse']);

    $response = $this->getJson('/api/list-data/warehouses');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'message',
            'data' => [
                '*' => ['id', 'name', 'address_line_1', 'address_line_2', 'city', 'state', 'postal_code', 'country']
            ]
        ])
        ->assertJsonCount(1, 'data')
        ->assertJsonFragment(['name' => 'Main Warehouse']);
});

it('can get customers list', function () {
    Customer::factory()->create(['is_active' => true, 'name' => 'Active Customer']);
    Customer::factory()->create(['is_active' => false, 'name' => 'Inactive Customer']);

    $response = $this->getJson('/api/list-data/customers');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'message',
            'data' => [
                '*' => ['id', 'name', 'email', 'mobile']
            ]
        ])
        ->assertJsonCount(1, 'data')
        ->assertJsonFragment(['name' => 'Active Customer']);
});

// it('returns error for invalid list type', function () {
//     $response = $this->getJson('/api/list-data/invalid-type');

//     $response->assertStatus(200)
//         ->assertJson(['error' => 'Invalid list type']);
// });

it('warehouses list returns only active records', function () {
    Warehouse::factory()->count(3)->create(['is_active' => true]);
    Warehouse::factory()->count(2)->create(['is_active' => false]);

    $response = $this->getJson('/api/list-data/warehouses');

    $response->assertStatus(200)
        ->assertJsonCount(3, 'data');
});

it('customers list returns only active records', function () {
    Customer::factory()->count(5)->create(['is_active' => true]);
    Customer::factory()->count(3)->create(['is_active' => false]);

    $response = $this->getJson('/api/list-data/customers');

    $response->assertStatus(200)
        ->assertJsonCount(5, 'data');
});

it('returns empty array when no active warehouses', function () {
    Warehouse::factory()->count(2)->create(['is_active' => false]);

    $response = $this->getJson('/api/list-data/warehouses');

    $response->assertStatus(200)
        ->assertJsonCount(0, 'data');
});

it('returns empty array when no active customers', function () {
    Customer::factory()->count(2)->create(['is_active' => false]);

    $response = $this->getJson('/api/list-data/customers');

    $response->assertStatus(200)
        ->assertJsonCount(0, 'data');
});

it('response has correct message format', function () {
    $response = $this->getJson('/api/list-data/warehouses');

    $response->assertStatus(200)
        ->assertJsonFragment(['message' => 'warehouses data']);
});

it('can get currencies list', function () {
    Currency::factory()->create(['is_active' => true, 'name' => 'USD']);
    Currency::factory()->create(['is_active' => false, 'name' => 'EUR']);

    $response = $this->getJson('/api/list-data/currencies');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'message',
            'data' => [
                '*' => ['id', 'name', 'code', 'symbol']
            ]
        ])
        ->assertJsonCount(1, 'data')
        ->assertJsonFragment(['name' => 'USD']);
});

it('can get suppliers list', function () {
    Supplier::factory()->create(['is_active' => true, 'name' => 'Main Supplier']);
    Supplier::factory()->create(['is_active' => false, 'name' => 'Inactive Supplier']);

    $response = $this->getJson('/api/list-data/suppliers');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'message',
            'data' => [
                '*' => ['id', 'name', 'email', 'phone']
            ]
        ])
        ->assertJsonCount(1, 'data')
        ->assertJsonFragment(['name' => 'Main Supplier']);
});

it('can get customer payment terms list', function () {
    CustomerPaymentTerm::factory()->create(['is_active' => true, 'name' => 'Net 30']);
    CustomerPaymentTerm::factory()->create(['is_active' => false, 'name' => 'Net 60']);

    $response = $this->getJson('/api/list-data/customerPaymentTerms');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'message',
            'data' => [
                '*' => ['id', 'name', 'days']
            ]
        ])
        ->assertJsonCount(1, 'data')
        ->assertJsonFragment(['name' => 'Net 30']);
});

it('can get item brands list', function () {
    ItemBrand::factory()->create(['is_active' => true, 'name' => 'Nike']);
    ItemBrand::factory()->create(['is_active' => false, 'name' => 'Adidas']);

    $response = $this->getJson('/api/list-data/itemBrands');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'message',
            'data' => [
                '*' => ['id', 'name']
            ]
        ])
        ->assertJsonCount(1, 'data')
        ->assertJsonFragment(['name' => 'Nike']);
});

it('can get tax codes list', function () {
    TaxCode::factory()->create(['is_active' => true, 'name' => 'VAT 10%']);
    TaxCode::factory()->create(['is_active' => false, 'name' => 'VAT 15%']);

    $response = $this->getJson('/api/list-data/taxCodes');

    $response->assertStatus(200)
        ->assertJsonStructure([
            'message',
            'data' => [
                '*' => ['id', 'name', 'tax_percent']
            ]
        ])
        ->assertJsonCount(1, 'data')
        ->assertJsonFragment(['name' => 'VAT 10%']);
});
