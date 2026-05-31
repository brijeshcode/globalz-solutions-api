<?php

use App\Models\Setups\Supplier;
use App\Models\Suppliers\Purchase;
use Tests\Feature\Suppliers\Purchases\Concerns\HasPurchaseSetup;

uses(HasPurchaseSetup::class);

uses()->group('api', 'suppliers', 'purchases');

beforeEach(function () {
    $this->setUpPurchases();

    // Ensure a known starting balance
    $this->supplier->update(['current_balance' => 0]);
});

// ─── created event ────────────────────────────────────────────────────────────

it('increases supplier balance when a purchase is created', function () {
    // EUR @ 1.0 (multiply): price=100, qty=2 → total_usd=200
    $this->postJson(route('suppliers.purchases.store'), $this->purchasePayload([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item1->id, 'price' => 100.00, 'quantity' => 2]],
    ]))->assertCreated();

    expect((float) $this->supplier->fresh()->current_balance)->toBe(200.0);
});

it('does not change supplier balance when purchase total is zero', function () {
    // price=0, qty=2 → total_usd=0 → no balance change
    $this->postJson(route('suppliers.purchases.store'), $this->purchasePayload([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item1->id, 'price' => 0.00, 'quantity' => 2]],
    ]))->assertCreated();

    expect((float) $this->supplier->fresh()->current_balance)->toBe(0.0);
});

// ─── updated event — amount change ────────────────────────────────────────────

it('adjusts supplier balance when purchase amount increases', function () {
    $response = $this->postJson(route('suppliers.purchases.store'), $this->purchasePayload([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item1->id, 'price' => 100.00, 'quantity' => 2]],
    ]))->assertCreated();

    expect((float) $this->supplier->fresh()->current_balance)->toBe(200.0);

    $purchase     = Purchase::find($response->json('data.id'));
    $purchaseItem = $purchase->purchaseItems->first();

    // Increase qty 2 → 3: total_usd 200 → 300, balance += 100
    $this->putJson(route('suppliers.purchases.update', $purchase), [
        'items' => [['id' => $purchaseItem->id, 'item_id' => $this->item1->id, 'price' => 100.00, 'quantity' => 3]],
    ])->assertOk();

    expect((float) $this->supplier->fresh()->current_balance)->toBe(300.0);
});

it('adjusts supplier balance when purchase amount decreases', function () {
    $response = $this->postJson(route('suppliers.purchases.store'), $this->purchasePayload([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item1->id, 'price' => 100.00, 'quantity' => 3]],
    ]))->assertCreated();

    expect((float) $this->supplier->fresh()->current_balance)->toBe(300.0);

    $purchase     = Purchase::find($response->json('data.id'));
    $purchaseItem = $purchase->purchaseItems->first();

    // Decrease qty 3 → 2: total_usd 300 → 200, balance -= 100
    $this->putJson(route('suppliers.purchases.update', $purchase), [
        'items' => [['id' => $purchaseItem->id, 'item_id' => $this->item1->id, 'price' => 100.00, 'quantity' => 2]],
    ])->assertOk();

    expect((float) $this->supplier->fresh()->current_balance)->toBe(200.0);
});

// ─── updated event — supplier change ─────────────────────────────────────────

it('moves supplier balance when supplier changes on update', function () {
    $newSupplier = Supplier::factory()->create(['current_balance' => 0]);

    $response = $this->postJson(route('suppliers.purchases.store'), $this->purchasePayload([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item1->id, 'price' => 100.00, 'quantity' => 2]],
    ]))->assertCreated();

    expect((float) $this->supplier->fresh()->current_balance)->toBe(200.0);
    expect((float) $newSupplier->fresh()->current_balance)->toBe(0.0);

    $purchase     = Purchase::find($response->json('data.id'));
    $purchaseItem = $purchase->purchaseItems->first();

    $this->putJson(route('suppliers.purchases.update', $purchase), [
        'supplier_id' => $newSupplier->id,
        'items'       => [['id' => $purchaseItem->id, 'item_id' => $this->item1->id, 'price' => 100.00, 'quantity' => 2]],
    ])->assertOk();

    expect((float) $this->supplier->fresh()->current_balance)->toBe(0.0);
    expect((float) $newSupplier->fresh()->current_balance)->toBe(200.0);
});

// ─── deleted event ────────────────────────────────────────────────────────────

it('reduces supplier balance when a purchase is deleted', function () {
    $response = $this->postJson(route('suppliers.purchases.store'), $this->purchasePayload([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item1->id, 'price' => 100.00, 'quantity' => 2]],
    ]))->assertCreated();

    expect((float) $this->supplier->fresh()->current_balance)->toBe(200.0);

    $purchase = Purchase::find($response->json('data.id'));

    $this->deleteJson(route('suppliers.purchases.destroy', $purchase))->assertNoContent();

    expect((float) $this->supplier->fresh()->current_balance)->toBe(0.0);
});

it('reduces supplier balance when a delivered purchase is deleted', function () {
    $purchase = $this->createPurchaseViaApi([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item1->id, 'price' => 100.00, 'quantity' => 2]],
    ]);

    $balanceAfterDelivery = (float) $this->supplier->fresh()->current_balance;

    $this->deleteJson(route('suppliers.purchases.destroy', $purchase))->assertNoContent();

    expect((float) $this->supplier->fresh()->current_balance)->toBe(0.0);
    expect($balanceAfterDelivery)->toBe(200.0);
});
