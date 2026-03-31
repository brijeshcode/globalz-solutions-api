<?php

use App\Models\Inventory\Inventory;
use App\Models\Suppliers\Purchase;
use Tests\Feature\Suppliers\Purchases\Concerns\HasPurchaseSetup;

uses(HasPurchaseSetup::class);

uses()->group('api', 'suppliers', 'purchases');

beforeEach(function () {
    $this->setUpPurchases();
});

it('updates purchase with item sync', function () {
    $purchase     = $this->createPurchaseViaApi([
        'currency_rate'           => 1.0,
        'supplier_invoice_number' => 'INV-INITIAL',
        'items'                   => [
            ['item_id' => $this->item1->id, 'quantity' => 2, 'price' => 50.00],
        ],
    ]);
    $existingItem = $purchase->purchaseItems->first();

    $this->putJson(route('suppliers.purchases.update', $purchase), [
        'supplier_invoice_number' => 'INV-UPDATED',
        'currency_rate'           => 1.15,
        'items'                   => [
            ['id' => $existingItem->id, 'item_id' => $this->item1->id, 'price' => 55.00, 'quantity' => 4],
            ['item_id' => $this->item2->id, 'price' => 120.00, 'quantity' => 2],
        ],
    ])->assertOk();

    $purchase->refresh();
    expect($purchase->supplier_invoice_number)->toBe('INV-UPDATED')
        ->and($purchase->currency_rate)->toBe('1.150000')
        ->and($purchase->purchaseItems)->toHaveCount(2);

    $existingItem->refresh();
    expect($existingItem->price)->toBe('55.00000')
        ->and($existingItem->quantity)->toBe(4);

    $inventory = Inventory::where('warehouse_id', $this->warehouse->id)->where('item_id', $this->item1->id)->first();
    expect($inventory->quantity)->toBe('4');
});

it('adjusts inventory when item quantity is reduced', function () {
    $purchase     = $this->createPurchaseViaApi([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item1->id, 'quantity' => 5, 'price' => 100.00]],
    ]);
    $purchaseItem = $purchase->purchaseItems->first();

    $this->putJson(route('suppliers.purchases.update', $purchase), [
        'items' => [
            ['id' => $purchaseItem->id, 'item_id' => $this->item1->id, 'quantity' => 2, 'price' => 100.00],
        ],
    ])->assertOk();

    $inventory = Inventory::where('warehouse_id', $this->warehouse->id)->where('item_id', $this->item1->id)->first();
    expect($inventory->quantity)->toBe('2');
});

it('validates that at least one item is required', function () {
    $purchase = $this->createPurchaseViaApi([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item1->id, 'quantity' => 5, 'price' => 100.00]],
    ]);

    $this->putJson(route('suppliers.purchases.update', $purchase), ['items' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['items']);
});

it('prevents removing items when inventory was already sold', function () {
    $purchase = $this->createPurchaseViaApi([
        'currency_rate' => 1.0,
        'items'         => [
            ['item_id' => $this->item1->id, 'quantity' => 100, 'price' => 50.00],
            ['item_id' => $this->item2->id, 'quantity' => 200, 'price' => 30.00],
        ],
    ]);

    // Simulate sales
    Inventory::where('warehouse_id', $this->warehouse->id)->where('item_id', $this->item1->id)->update(['quantity' => 40]);
    Inventory::where('warehouse_id', $this->warehouse->id)->where('item_id', $this->item2->id)->update(['quantity' => 50]);

    $item2PurchaseItem = $purchase->purchaseItems->where('item_id', $this->item2->id)->first();

    $response = $this->putJson(route('suppliers.purchases.update', $purchase), [
        'supplier_invoice_number' => 'INV-TEST-001',
        'items'                   => [
            ['id' => $item2PurchaseItem->id, 'item_id' => $this->item2->id, 'quantity' => 200, 'price' => 30.00],
        ],
    ]);

    $response->assertStatus(422);
    expect($response->json('message'))
        ->toContain('Cannot remove')
        ->toContain($this->item1->name)
        ->toContain('100 units')
        ->toContain('40 units')
        ->toContain('60 already sold/used');

    $purchase->refresh();
    expect($purchase->purchaseItems)->toHaveCount(2);
});

it('allows removing items when full inventory is available', function () {
    $purchase = $this->createPurchaseViaApi([
        'currency_rate' => 1.0,
        'items'         => [
            ['item_id' => $this->item1->id, 'quantity' => 50, 'price' => 25.00],
            ['item_id' => $this->item2->id, 'quantity' => 100, 'price' => 15.00],
        ],
    ]);

    $item2PurchaseItem = $purchase->purchaseItems->where('item_id', $this->item2->id)->first();

    $this->putJson(route('suppliers.purchases.update', $purchase), [
        'supplier_invoice_number' => 'INV-TEST-002',
        'items'                   => [
            ['id' => $item2PurchaseItem->id, 'item_id' => $this->item2->id, 'quantity' => 100, 'price' => 15.00],
        ],
    ])->assertOk();

    $purchase->refresh();
    expect($purchase->purchaseItems)->toHaveCount(1)
        ->and($purchase->purchaseItems->first()->item_id)->toBe($this->item2->id);

    $inventory1 = Inventory::where('warehouse_id', $this->warehouse->id)->where('item_id', $this->item1->id)->first();
    expect($inventory1->quantity)->toBe('0');
});

it('handles inventory and prices when item is removed from purchase', function () {
    $purchase = $this->createPurchaseViaApi([
        'currency_rate' => 1.0,
        'items'         => [
            ['item_id' => $this->item1->id, 'quantity' => 10, 'price' => 50.00],
            ['item_id' => $this->item2->id, 'quantity' => 5, 'price' => 80.00],
        ],
    ]);

    $purchaseItem1 = $purchase->purchaseItems->where('item_id', $this->item1->id)->first();

    $this->putJson(route('suppliers.purchases.update', $purchase), [
        'items' => [
            ['id' => $purchaseItem1->id, 'item_id' => $this->item1->id, 'quantity' => 8, 'price' => 55.00],
        ],
    ])->assertOk();

    $inventory1 = Inventory::where('warehouse_id', $this->warehouse->id)->where('item_id', $this->item1->id)->first();
    expect($inventory1->quantity)->toBe('8');

    $inventory2 = Inventory::where('warehouse_id', $this->warehouse->id)->where('item_id', $this->item2->id)->first();
    expect($inventory2->quantity)->toBe('0');

    $purchase->refresh();
    expect($purchase->purchaseItems)->toHaveCount(1);
});
