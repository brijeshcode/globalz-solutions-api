<?php

use App\Models\Setups\Supplier;
use App\Models\Suppliers\PurchaseReturn;
use Tests\Feature\Suppliers\PurchaseReturns\Concerns\HasPurchaseReturnSetup;

beforeEach(function () {
    $this->setUpPurchaseReturns();
});

it('lists purchase returns with correct structure', function () {
    PurchaseReturn::factory()->count(3)->create([
        'supplier_id'  => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'currency_id'  => $this->currency->id,
    ]);

    $this->getJson(route('suppliers.purchase-returns.index'))
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

    PurchaseReturn::factory()->create([
        'supplier_id'  => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'currency_id'  => $this->currency->id,
    ]);
    PurchaseReturn::factory()->create([
        'supplier_id'  => $otherSupplier->id,
        'warehouse_id' => $this->warehouse->id,
        'currency_id'  => $this->currency->id,
    ]);

    $this->getJson(route('suppliers.purchase-returns.index', ['supplier_id' => $this->supplier->id]))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('filters by shipping status', function () {
    PurchaseReturn::factory()->create([
        'shipping_status' => 'Waiting',
        'supplier_id'     => $this->supplier->id,
        'warehouse_id'    => $this->warehouse->id,
        'currency_id'     => $this->currency->id,
    ]);
    PurchaseReturn::factory()->create([
        'shipping_status' => 'Shipped',
        'supplier_id'     => $this->supplier->id,
        'warehouse_id'    => $this->warehouse->id,
        'currency_id'     => $this->currency->id,
    ]);

    $this->getJson(route('suppliers.purchase-returns.index', ['shipping_status' => 'Waiting']))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('paginates results', function () {
    PurchaseReturn::factory()->count(7)->create([
        'supplier_id'  => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'currency_id'  => $this->currency->id,
    ]);

    $response = $this->getJson(route('suppliers.purchase-returns.index', ['per_page' => 3]))->assertOk();

    expect($response->json('data'))->toHaveCount(3)
        ->and($response->json('pagination.total'))->toBe(7)
        ->and($response->json('pagination.per_page'))->toBe(3)
        ->and($response->json('pagination.last_page'))->toBe(3);
});
