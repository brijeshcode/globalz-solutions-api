<?php

use App\Models\Inventory\Inventory;
use App\Models\Suppliers\Purchase;
use App\Models\Suppliers\SupplierItemPrice;
use App\Models\User;
use Tests\Feature\Suppliers\Purchases\Concerns\HasPurchaseSetup;

uses(HasPurchaseSetup::class);

uses()->group('api', 'suppliers', 'purchases');

beforeEach(function () {
    $this->setUpPurchases();
});

it('allows super admin to change status', function () {
    $purchase = Purchase::factory()->create([
        'supplier_id'  => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'status'       => 'Waiting',
    ]);

    $this->patchJson(route('suppliers.purchases.changeStatus', $purchase), ['status' => 'Shipped'])
        ->assertOk()
        ->assertJson(['data' => ['status' => 'Shipped']]);
});

it('denies non-warehouse-manager role from changing status', function () {
    $salesman = User::factory()->create(['role' => User::ROLE_SALESMAN]);
    $this->actingAs($salesman, 'sanctum');

    $purchase = Purchase::factory()->create([
        'supplier_id'  => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
    ]);

    $this->patchJson(route('suppliers.purchases.changeStatus', $purchase), ['status' => 'Shipped'])
        ->assertUnprocessable()
        ->assertJson(['message' => 'Only warehouse manager can change the status.']);
});

it('validates status is required', function () {
    $purchase = Purchase::factory()->create(['supplier_id' => $this->supplier->id]);

    $this->patchJson(route('suppliers.purchases.changeStatus', $purchase), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
});

it('validates status must be a valid value', function (string $status) {
    $purchase = Purchase::factory()->create(['supplier_id' => $this->supplier->id]);

    $this->patchJson(route('suppliers.purchases.changeStatus', $purchase), ['status' => $status])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status']);
})->with([
    'invalid string' => 'Pending',
    'lowercase'      => 'delivered',
    'empty string'   => '',
]);

it('prevents status change when purchase is already delivered', function () {
    // Must go through the service — the creating event forces status='Waiting' on factory creates,
    // so only createPurchaseViaApi produces a genuinely Delivered purchase.
    $purchase = $this->createPurchaseViaApi([
        'items' => [['item_id' => $this->item1->id, 'price' => 50.00, 'quantity' => 5]],
    ]);

    $this->patchJson(route('suppliers.purchases.changeStatus', $purchase), ['status' => 'Shipped'])
        ->assertUnprocessable()
        ->assertJson(['message' => 'Cannot change status. Purchase is already delivered and inventory has been added.']);
});

it('changes status from Waiting to Shipped', function () {
    $purchase = Purchase::factory()->create([
        'supplier_id'  => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'status'       => 'Waiting',
    ]);

    $this->patchJson(route('suppliers.purchases.changeStatus', $purchase), ['status' => 'Shipped'])
        ->assertOk()
        ->assertJson(['data' => ['status' => 'Shipped']]);

    expect($purchase->fresh()->status)->toBe('Shipped');
});

it('changes status from Shipped back to Waiting', function () {
    $purchase = Purchase::factory()->create([
        'supplier_id'  => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'status'       => 'Shipped',
    ]);

    $this->patchJson(route('suppliers.purchases.changeStatus', $purchase), ['status' => 'Waiting'])
        ->assertOk()
        ->assertJson(['data' => ['status' => 'Waiting']]);

    expect($purchase->fresh()->status)->toBe('Waiting');
});

it('delivers a purchase and updates inventory', function () {
    $response = $this->postJson(route('suppliers.purchases.store'), $this->purchasePayload([
        'items' => [
            ['item_id' => $this->item1->id, 'price' => 50.00, 'quantity' => 10],
        ],
    ]))->assertCreated();

    $purchase = Purchase::find($response->json('data.id'));

    $this->patchJson(route('suppliers.purchases.changeStatus', $purchase), ['status' => 'Delivered'])
        ->assertOk()
        ->assertJson(['data' => ['status' => 'Delivered']]);

    expect($purchase->fresh()->status)->toBe('Delivered');

    $inventory = Inventory::where('warehouse_id', $this->warehouse->id)
        ->where('item_id', $this->item1->id)
        ->first();

    expect($inventory)->not()->toBeNull()
        ->and((int) $inventory->quantity)->toBe(10);
});

it('creates supplier item prices when delivering', function () {
    $response = $this->postJson(route('suppliers.purchases.store'), $this->purchasePayload([
        'items' => [
            ['item_id' => $this->item1->id, 'price' => 75.00, 'quantity' => 5],
        ],
    ]))->assertCreated();

    $purchase = Purchase::find($response->json('data.id'));

    $this->patchJson(route('suppliers.purchases.changeStatus', $purchase), ['status' => 'Delivered'])
        ->assertOk();

    $supplierPrice = SupplierItemPrice::where('supplier_id', $this->supplier->id)
        ->where('item_id', $this->item1->id)
        ->where('is_current', true)
        ->first();

    expect($supplierPrice)->not()->toBeNull()
        ->and($supplierPrice->price)->toBe('75.0000');
});

it('returns 404 for non-existent purchase', function () {
    $this->patchJson(route('suppliers.purchases.changeStatus', 999), ['status' => 'Shipped'])
        ->assertNotFound();
});
