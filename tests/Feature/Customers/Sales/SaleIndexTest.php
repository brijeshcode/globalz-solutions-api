<?php

use App\Models\Customers\Sale;
use App\Models\Setups\Generals\Currencies\Currency;
use App\Models\Setups\Warehouse;
use Tests\Feature\Customers\Sales\Concerns\HasSaleSetup;

uses(HasSaleSetup::class);

beforeEach(function () {
    $this->setUpSales();
});

it('lists sales with correct structure', function () {
    $this->createApprovedSale();
    $this->createApprovedSale();
    $this->createApprovedSale();

    $this->getJson(route('customers.sales.index'))
        ->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => ['*' => ['id', 'code', 'sale_code', 'date', 'prefix', 'warehouse', 'currency', 'total', 'total_usd']],
            'pagination',
        ])
        ->assertJsonCount(3, 'data');
});

it('filters by warehouse', function () {
    $other = Warehouse::factory()->create(['name' => 'Other Warehouse']);

    $this->createApprovedSale(['warehouse_id' => $this->warehouse->id]);
    $this->createApprovedSale(['warehouse_id' => $other->id]);

    $this->getJson(route('customers.sales.index', ['warehouse_id' => $this->warehouse->id]))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('filters by currency', function () {
    $other = Currency::factory()->usd()->create();

    $this->createApprovedSale(['currency_id' => $this->currency->id]);
    $this->createApprovedSale(['currency_id' => $other->id]);

    $this->getJson(route('customers.sales.index', ['currency_id' => $this->currency->id]))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('filters by date range', function () {
    $this->createApprovedSale(['date' => '2025-01-01']);
    $this->createApprovedSale(['date' => '2025-02-15']);
    $this->createApprovedSale(['date' => '2025-03-30']);

    $this->getJson(route('customers.sales.index', ['date_from' => '2025-02-01', 'date_to' => '2025-02-28']))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('searches by code', function () {
    $this->createApprovedSale(['code' => '001001']);
    $this->createApprovedSale(['code' => '001002']);

    $response = $this->getJson(route('customers.sales.index', ['search' => '001001']))->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.code'))->toBe('001001');
});

it('searches by client PO number', function () {
    $this->createApprovedSale(['client_po_number' => 'PO-12345']);
    $this->createApprovedSale(['client_po_number' => 'PO-67890']);

    $response = $this->getJson(route('customers.sales.index', ['search' => 'PO-12345']))->assertOk();

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.client_po_number'))->toBe('PO-12345');
});

it('paginates results', function () {
    Sale::factory()->count(7)->create([
        'warehouse_id' => $this->warehouse->id,
        'approved_by'  => $this->admin->id,
        'approved_at'  => now(),
    ]);

    $response = $this->getJson(route('customers.sales.index', ['per_page' => 3]))->assertOk();

    expect($response->json('data'))->toHaveCount(3)
        ->and($response->json('pagination.total'))->toBe(7)
        ->and($response->json('pagination.last_page'))->toBe(3);
});
