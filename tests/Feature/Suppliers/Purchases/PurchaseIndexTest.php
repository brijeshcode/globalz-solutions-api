<?php

use App\Models\Setups\Supplier;
use App\Models\Suppliers\Purchase;
use Tests\Feature\Suppliers\Purchases\Concerns\HasPurchaseSetup;

uses(HasPurchaseSetup::class);

uses()->group('api', 'suppliers', 'purchases');

beforeEach(function () {
    $this->setUpPurchases();
});

it('lists purchases with correct structure', function () {
    Purchase::factory()->count(3)->create([
        'supplier_id'  => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'currency_id'  => $this->currency->id,
    ]);

    $this->getJson(route('suppliers.purchases.index'))
        ->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => ['*' => ['id', 'code', 'date', 'supplier', 'warehouse', 'currency', 'final_total_usd']],
            'pagination',
        ])
        ->assertJsonCount(3, 'data');
});

it('filters by supplier', function () {
    $otherSupplier = Supplier::factory()->create(['name' => 'Other Supplier']);

    Purchase::factory()->create(['supplier_id' => $this->supplier->id]);
    Purchase::factory()->create(['supplier_id' => $otherSupplier->id]);

    $this->getJson(route('suppliers.purchases.index', ['supplier_id' => $this->supplier->id]))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('filters by date range', function () {
    Purchase::factory()->create(['date' => '2025-01-01']);
    Purchase::factory()->create(['date' => '2025-02-15']);
    Purchase::factory()->create(['date' => '2025-03-30']);

    $this->getJson(route('suppliers.purchases.index', ['from_date' => '2025-02-01', 'to_date' => '2025-02-28']))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('searches by code', function () {
    Purchase::factory()->create(['code' => 'PUR-001001', 'supplier_id' => $this->supplier->id]);
    Purchase::factory()->create(['code' => 'PUR-001002', 'supplier_id' => $this->supplier->id]);

    $data = $this->getJson(route('suppliers.purchases.index', ['search' => 'PUR-001001']))
        ->assertOk()
        ->json('data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['code'])->toBe('PUR-001001');
});

it('searches by supplier invoice number', function () {
    Purchase::factory()->create(['supplier_invoice_number' => 'INV-12345', 'supplier_id' => $this->supplier->id]);
    Purchase::factory()->create(['supplier_invoice_number' => 'INV-67890', 'supplier_id' => $this->supplier->id]);

    $data = $this->getJson(route('suppliers.purchases.index', ['search' => 'INV-12345']))
        ->assertOk()
        ->json('data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['supplier_invoice_number'])->toBe('INV-12345');
});

it('paginates results', function () {
    Purchase::factory()->count(7)->create(['supplier_id' => $this->supplier->id]);

    $response = $this->getJson(route('suppliers.purchases.index', ['per_page' => 3]))->assertOk();

    expect($response->json('data'))->toHaveCount(3)
        ->and($response->json('pagination.total'))->toBe(7)
        ->and($response->json('pagination.per_page'))->toBe(3)
        ->and($response->json('pagination.last_page'))->toBe(3);
});
