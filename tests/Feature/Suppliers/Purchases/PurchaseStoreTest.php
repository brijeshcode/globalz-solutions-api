<?php

use App\Models\Inventory\Inventory;
use App\Models\Inventory\ItemPrice;
use App\Models\Suppliers\Purchase;
use App\Models\Suppliers\SupplierItemPrice;
use Tests\Feature\Suppliers\Purchases\Concerns\HasPurchaseSetup;

uses(HasPurchaseSetup::class);

uses()->group('api', 'suppliers', 'purchases');

beforeEach(function () {
    $this->setUpPurchases();
});

it('creates a purchase with items and updates inventory', function () {
    $data = $this->purchasePayload([
        'shipping_fee_usd' => 50.00,
        'customs_fee_usd'  => 25.00,
        'note'             => 'Test purchase with multiple items',
        'items'            => [
            [
                'item_id'          => $this->item1->id,
                'price'            => 100.00,
                'quantity'         => 5,
                'discount_percent' => 10,
                'note'             => 'First item with discount',
            ],
            [
                'item_id'         => $this->item2->id,
                'price'           => 200.00,
                'quantity'        => 3,
                'discount_amount' => 15.00,
                'note'            => 'Second item with fixed discount',
            ],
        ],
    ]);

    $this->postJson(route('suppliers.purchases.store'), $data)
        ->assertCreated()
        ->assertJsonStructure([
            'message',
            'data' => ['id', 'code', 'items', 'supplier', 'warehouse'],
        ]);

    $this->assertDatabaseHas('purchases', [
        'supplier_id'             => $this->supplier->id,
        'warehouse_id'            => $this->warehouse->id,
        'supplier_invoice_number' => 'INV-2025-001',
    ]);

    $purchase = Purchase::latest()->first();
    $this->patchJson(route('suppliers.purchases.changeStatus', $purchase), ['status' => 'Delivered'])->assertOk();

    expect($purchase->purchaseItems)->toHaveCount(2);

    $inventory1 = Inventory::where('warehouse_id', $this->warehouse->id)->where('item_id', $this->item1->id)->first();
    expect($inventory1->quantity)->toBe('5');

    $inventory2 = Inventory::where('warehouse_id', $this->warehouse->id)->where('item_id', $this->item2->id)->first();
    expect($inventory2->quantity)->toBe('3');
});

it('creates supplier item prices on delivery', function () {
    $data = $this->purchasePayload([
        'items' => [
            ['item_id' => $this->item1->id, 'price' => 100.00, 'quantity' => 5],
        ],
    ]);

    $response = $this->postJson(route('suppliers.purchases.store'), $data)->assertCreated();
    $purchase  = Purchase::find($response->json('data.id'));
    $this->patchJson(route('suppliers.purchases.changeStatus', $purchase), ['status' => 'Delivered'])->assertOk();

    $supplierPrice = SupplierItemPrice::where('supplier_id', $this->supplier->id)
        ->where('item_id', $this->item1->id)
        ->where('is_current', true)
        ->first();

    expect($supplierPrice)->not()->toBeNull()
        ->and($supplierPrice->price)->toBe('100.0000')
        ->and($supplierPrice->price_usd)->toBeGreaterThan(0);
});

it('creates item price record on delivery', function () {
    $data = $this->purchasePayload([
        'items' => [
            ['item_id' => $this->item1->id, 'price' => 100.00, 'quantity' => 5],
        ],
    ]);

    $response = $this->postJson(route('suppliers.purchases.store'), $data)->assertCreated();
    $purchase  = Purchase::find($response->json('data.id'));
    $this->patchJson(route('suppliers.purchases.changeStatus', $purchase), ['status' => 'Delivered'])->assertOk();

    $itemPrice = ItemPrice::where('item_id', $this->item1->id)->first();
    expect($itemPrice)->not()->toBeNull()
        ->and($itemPrice->price_usd)->toBeGreaterThan(0);
});

it('auto-generates purchase code when not provided', function () {
    $response = $this->postJson(route('suppliers.purchases.store'), $this->purchasePayload([
        'items' => [['item_id' => $this->item1->id, 'price' => 100.00, 'quantity' => 1]],
    ]))->assertCreated();

    $purchase = Purchase::where('supplier_id', $this->supplier->id)->first();
    expect($purchase->code)->not()->toBeNull();
});

it('sets created_by and updated_by automatically', function () {
    $purchase = Purchase::factory()->create([
        'supplier_id'  => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
    ]);

    expect($purchase->created_by)->toBe($this->user->id)
        ->and($purchase->updated_by)->toBe($this->user->id);
});

it('validates required fields', function () {
    $this->postJson(route('suppliers.purchases.store'), [
        'supplier_id'  => 999,
        'warehouse_id' => null,
        'currency_id'  => null,
        'items'        => [
            ['item_id' => 999, 'price' => -10, 'quantity' => 0],
        ],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'supplier_id',
            'warehouse_id',
            'currency_id',
            'items.0.item_id',
            'items.0.price',
            'items.0.quantity',
        ]);
});

it('only creates new supplier item price when price changes', function () {
    $initialPrice = SupplierItemPrice::factory()->create([
        'supplier_id' => $this->supplier->id,
        'item_id'     => $this->item1->id,
        'price'       => 100.00,
        'is_current'  => true,
    ]);

    // Same price — no new record
    $r1       = $this->postJson(route('suppliers.purchases.store'), $this->purchasePayload([
        'items' => [['item_id' => $this->item1->id, 'price' => 100.00, 'quantity' => 1]],
    ]))->assertCreated();
    $purchase1 = Purchase::find($r1->json('data.id'));
    $this->patchJson(route('suppliers.purchases.changeStatus', $purchase1), ['status' => 'Delivered'])->assertOk();

    expect(SupplierItemPrice::where('supplier_id', $this->supplier->id)->where('item_id', $this->item1->id)->where('is_current', true)->count())->toBe(1);

    // Different price — new record
    $r2       = $this->postJson(route('suppliers.purchases.store'), $this->purchasePayload([
        'supplier_invoice_number' => 'INV-2025-002',
        'items' => [['item_id' => $this->item1->id, 'price' => 120.00, 'quantity' => 1]],
    ]))->assertCreated();
    $purchase2 = Purchase::find($r2->json('data.id'));
    $this->patchJson(route('suppliers.purchases.changeStatus', $purchase2), ['status' => 'Delivered'])->assertOk();

    $currentPrice = SupplierItemPrice::where('supplier_id', $this->supplier->id)->where('item_id', $this->item1->id)->where('is_current', true)->first();
    expect($currentPrice->price)->toBe('120.0000');

    $initialPrice->refresh();
    expect($initialPrice->is_current)->toBeFalse();
});
