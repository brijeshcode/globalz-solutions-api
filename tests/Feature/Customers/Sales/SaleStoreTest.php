<?php

use App\Models\Customers\Sale;
use App\Models\Customers\SaleItems;
use App\Services\Inventory\InventoryService;
use Tests\Feature\Customers\Sales\Concerns\HasSaleSetup;

uses(HasSaleSetup::class);

beforeEach(function () {
    $this->setUpSales();
});

it('creates a sale with items and reduces inventory', function () {
    InventoryService::set($this->item1->id, $this->warehouse->id, 100, 'Reset');
    InventoryService::set($this->item2->id, $this->warehouse->id, 50, 'Reset');

    $before1 = InventoryService::getQuantity($this->item1->id, $this->warehouse->id);
    $before2 = InventoryService::getQuantity($this->item2->id, $this->warehouse->id);

    $this->postJson(route('customers.sales.store'), $this->salePayload([
        'client_po_number' => 'PO-2025-001',
        'note'             => 'Test sale with multiple items',
        'sub_total'        => 520.00,
        'total'            => 520.00,
        'total_usd'        => 416.00,
        'items'            => [
            ['item_id' => $this->item1->id, 'price' => 100.00, 'quantity' => 3, 'total_price' => 300.00, 'note' => 'First item'],
            ['item_id' => $this->item2->id, 'price' => 110.00, 'quantity' => 2, 'total_price' => 220.00, 'note' => 'Second item'],
        ],
    ]))->assertCreated()
       ->assertJsonStructure(['message', 'data' => ['id', 'code', 'items', 'warehouse', 'currency']]);

    $this->assertDatabaseHas('sales', ['warehouse_id' => $this->warehouse->id, 'client_po_number' => 'PO-2025-001', 'prefix' => 'INV']);

    $sale = Sale::latest()->first();
    expect($sale->saleItems)->toHaveCount(2);

    expect(InventoryService::getQuantity($this->item1->id, $this->warehouse->id))->toBe($before1 - 3);
    expect(InventoryService::getQuantity($this->item2->id, $this->warehouse->id))->toBe($before2 - 2);
});

it('sets created_by and updated_by automatically', function () {
    $sale = $this->createApprovedSale();

    expect($sale->created_by)->toBe($this->admin->id)
        ->and($sale->updated_by)->toBe($this->admin->id);

    $sale->update(['note' => 'Updated note']);
    expect($sale->fresh()->updated_by)->toBe($this->admin->id);
});

it('requires all mandatory fields', function () {
    $this->postJson(route('customers.sales.store'), [
        'warehouse_id' => null,
        'currency_id'  => null,
        'total'        => -100,
        'items'        => [
            ['item_id' => 999, 'price' => -10, 'quantity' => 0],
        ],
    ])->assertUnprocessable()
      ->assertJsonValidationErrors(['warehouse_id', 'currency_id', 'items.0.item_id', 'items.0.price', 'items.0.quantity']);
});

it('rolls back when inventory is insufficient', function () {
    InventoryService::set($this->item1->id, $this->warehouse->id, 1, 'Low stock');

    $beforeCount = Sale::count();
    $beforeItems = SaleItems::count();
    $beforeInv   = InventoryService::getQuantity($this->item1->id, $this->warehouse->id);

    $this->postJson(route('customers.sales.store'), $this->salePayload([
        'items' => [['item_id' => $this->item1->id, 'price' => 100.00, 'quantity' => 5, 'total_price' => 500.00]],
    ]))->assertStatus(500);

    expect(Sale::count())->toBe($beforeCount)
        ->and(SaleItems::count())->toBe($beforeItems)
        ->and(InventoryService::getQuantity($this->item1->id, $this->warehouse->id))->toBe($beforeInv);
});

it('rolls back when validation fails mid-creation', function () {
    $beforeCount = Sale::count();
    $beforeItems = SaleItems::count();
    $beforeInv   = InventoryService::getQuantity($this->item1->id, $this->warehouse->id);

    $this->postJson(route('customers.sales.store'), $this->salePayload([
        'items' => [
            ['item_id' => $this->item1->id, 'price' => 100.00, 'quantity' => 2, 'total_price' => 200.00],
            ['item_id' => 999,              'price' => 150.00, 'quantity' => 1, 'total_price' => 150.00], // invalid
        ],
    ]))->assertUnprocessable();

    expect(Sale::count())->toBe($beforeCount)
        ->and(SaleItems::count())->toBe($beforeItems)
        ->and(InventoryService::getQuantity($this->item1->id, $this->warehouse->id))->toBe($beforeInv);
});

it('handles concurrent creation — blocks second sale when inventory runs out', function () {
    InventoryService::set($this->item1->id, $this->warehouse->id, 5, 'Limited stock');

    $payload = $this->salePayload([
        'items' => [['item_id' => $this->item1->id, 'price' => 100.00, 'quantity' => 3, 'total_price' => 300.00]],
    ]);

    $this->postJson(route('customers.sales.store'), $payload)->assertCreated();
    expect(InventoryService::getQuantity($this->item1->id, $this->warehouse->id))->toBe(2);

    $this->postJson(route('customers.sales.store'), $payload)->assertStatus(500);
    expect(InventoryService::getQuantity($this->item1->id, $this->warehouse->id))->toBe(2);

    expect(Sale::count())->toBe(1);
});
