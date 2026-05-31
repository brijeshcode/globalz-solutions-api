<?php

use App\Models\Inventory\ItemPrice;
use App\Models\Setups\Generals\Currencies\Currency;
use App\Models\Suppliers\Purchase;
use App\Models\Suppliers\SupplierItemPrice;
use Tests\Feature\Suppliers\Purchases\Concerns\HasPurchaseSetup;

uses(HasPurchaseSetup::class);

uses()->group('api', 'suppliers', 'purchases');

beforeEach(function () {
    $this->setUpPurchases();

    // EUR defaults to calculation_type='multiply' (migration default)
    $this->currency->update(['calculation_type' => 'multiply']);

    // Divide-type currency: 1 USD = 1.3 local units → toUsd = amount / rate
    $this->divideCurrency = Currency::factory()->create([
        'name'             => 'Test Divide Currency',
        'code'             => 'TDC',
        'calculation_type' => 'divide',
        'is_active'        => true,
    ]);
});

// ─── Multiply-type currency (EUR, rate = 1.25) ────────────────────────────────

it('stores correct total_price_usd for multiply-type currency', function () {
    // EUR @ 1.25: total_price = 100 * 5 = 500 local, USD = 500 * 1.25 = 625
    $response = $this->postJson(route('suppliers.purchases.store'), $this->purchasePayload([
        'currency_rate' => 1.25,
        'items'         => [
            ['item_id' => $this->item1->id, 'price' => 100.00, 'quantity' => 5],
        ],
    ]))->assertCreated();

    $purchase     = Purchase::find($response->json('data.id'));
    $purchaseItem = $purchase->purchaseItems->first();

    expect((float) $purchaseItem->total_price)->toBe(500.0)
        ->and((float) $purchaseItem->total_price_usd)->toBe(625.0)
        ->and((float) $purchaseItem->cost_per_item_usd)->toBe(125.0);
});

it('stores correct purchase-level USD totals for multiply-type currency', function () {
    // Two items: EUR @ 1.25
    // item1: 100 * 3 = 300 local → 375 USD
    // item2: 200 * 2 = 400 local → 500 USD
    // sub_total_usd = 875, final_total_usd = 875 (no discounts/expenses)
    $response = $this->postJson(route('suppliers.purchases.store'), $this->purchasePayload([
        'currency_rate' => 1.25,
        'items'         => [
            ['item_id' => $this->item1->id, 'price' => 100.00, 'quantity' => 3],
            ['item_id' => $this->item2->id, 'price' => 200.00, 'quantity' => 2],
        ],
    ]))->assertCreated();

    $purchase = Purchase::find($response->json('data.id'));

    expect((float) $purchase->sub_total_usd)->toBe(875.0)
        ->and((float) $purchase->final_total_usd)->toBe(875.0);
});

it('stores correct cost_per_item_usd on delivery for multiply-type currency', function () {
    // EUR @ 1.25: price=80, qty=4 → total_price_usd = 400 → cost_per_item_usd = 100
    $response = $this->postJson(route('suppliers.purchases.store'), $this->purchasePayload([
        'currency_rate' => 1.25,
        'items'         => [
            ['item_id' => $this->item1->id, 'price' => 80.00, 'quantity' => 4],
        ],
    ]))->assertCreated();

    $purchase = Purchase::find($response->json('data.id'));
    $this->patchJson(route('suppliers.purchases.changeStatus', $purchase), ['status' => 'Delivered'])->assertOk();

    $itemPrice = ItemPrice::where('item_id', $this->item1->id)->first();
    expect($itemPrice)->not()->toBeNull()
        ->and((float) $itemPrice->price_usd)->toBe(100.0);
});

it('stores correct supplier price_usd on delivery for multiply-type currency', function () {
    // EUR @ 1.25: price=80 per unit → price_usd = 80 * 1.25 / 1 = 100
    // cost_per_item_usd = (80 * 1.25) = 100
    $response = $this->postJson(route('suppliers.purchases.store'), $this->purchasePayload([
        'currency_rate' => 1.25,
        'items'         => [
            ['item_id' => $this->item1->id, 'price' => 80.00, 'quantity' => 4],
        ],
    ]))->assertCreated();

    $purchase = Purchase::find($response->json('data.id'));
    $this->patchJson(route('suppliers.purchases.changeStatus', $purchase), ['status' => 'Delivered'])->assertOk();

    $supplierPrice = SupplierItemPrice::where('supplier_id', $this->supplier->id)
        ->where('item_id', $this->item1->id)
        ->where('is_current', true)
        ->first();

    expect($supplierPrice)->not()->toBeNull()
        ->and((float) $supplierPrice->price)->toBe(80.0)
        ->and((float) $supplierPrice->price_usd)->toBe(100.0);
});

// ─── Divide-type currency (TDC, rate = 1.3) ───────────────────────────────────

it('stores correct total_price_usd for divide-type currency', function () {
    // TDC @ 1.3 (divide): total_price = 130 * 5 = 650 local, USD = 650 / 1.3 = 500
    $response = $this->postJson(route('suppliers.purchases.store'), $this->purchasePayload([
        'currency_id'   => $this->divideCurrency->id,
        'currency_rate' => 1.3,
        'items'         => [
            ['item_id' => $this->item1->id, 'price' => 130.00, 'quantity' => 5],
        ],
    ]))->assertCreated();

    $purchase     = Purchase::find($response->json('data.id'));
    $purchaseItem = $purchase->purchaseItems->first();

    expect((float) $purchaseItem->total_price)->toBe(650.0)
        ->and((float) $purchaseItem->total_price_usd)->toBe(500.0)
        ->and((float) $purchaseItem->cost_per_item_usd)->toBe(100.0);
});

it('stores correct purchase-level USD totals for divide-type currency', function () {
    // TDC @ 1.3 (divide)
    // item1: 260 * 2 = 520 local → 400 USD
    // item2: 130 * 3 = 390 local → 300 USD
    // sub_total_usd = 700
    $response = $this->postJson(route('suppliers.purchases.store'), $this->purchasePayload([
        'currency_id'   => $this->divideCurrency->id,
        'currency_rate' => 1.3,
        'items'         => [
            ['item_id' => $this->item1->id, 'price' => 260.00, 'quantity' => 2],
            ['item_id' => $this->item2->id, 'price' => 130.00, 'quantity' => 3],
        ],
    ]))->assertCreated();

    $purchase = Purchase::find($response->json('data.id'));

    expect((float) $purchase->sub_total_usd)->toBe(700.0)
        ->and((float) $purchase->final_total_usd)->toBe(700.0);
});

it('stores correct cost_per_item_usd on delivery for divide-type currency', function () {
    // TDC @ 1.3: price=130, qty=5 → total_price_usd=500 → cost_per_item_usd=100
    $response = $this->postJson(route('suppliers.purchases.store'), $this->purchasePayload([
        'currency_id'   => $this->divideCurrency->id,
        'currency_rate' => 1.3,
        'items'         => [
            ['item_id' => $this->item1->id, 'price' => 130.00, 'quantity' => 5],
        ],
    ]))->assertCreated();

    $purchase = Purchase::find($response->json('data.id'));
    $this->patchJson(route('suppliers.purchases.changeStatus', $purchase), ['status' => 'Delivered'])->assertOk();

    $itemPrice = ItemPrice::where('item_id', $this->item1->id)->first();
    expect($itemPrice)->not()->toBeNull()
        ->and((float) $itemPrice->price_usd)->toBe(100.0);
});

// ─── Discount with currency conversion ────────────────────────────────────────

it('applies line discount before currency conversion for multiply-type', function () {
    // EUR @ 1.25: price=100, qty=4, discount_percent=10
    // total_before_discount = 400, discount = 40, total_price = 360
    // total_price_usd = 360 * 1.25 = 450
    $response = $this->postJson(route('suppliers.purchases.store'), $this->purchasePayload([
        'currency_rate' => 1.25,
        'items'         => [
            ['item_id' => $this->item1->id, 'price' => 100.00, 'quantity' => 4, 'discount_percent' => 10],
        ],
    ]))->assertCreated();

    $purchase     = Purchase::find($response->json('data.id'));
    $purchaseItem = $purchase->purchaseItems->first();

    expect((float) $purchaseItem->total_price)->toBe(360.0)
        ->and((float) $purchaseItem->total_price_usd)->toBe(450.0)
        ->and((float) $purchaseItem->discount_amount)->toBe(40.0);
});

it('applies fixed discount before currency conversion for multiply-type', function () {
    // EUR @ 1.25: price=100, qty=2, discount_amount=50 (total line discount)
    // total_price = 200 - 50 = 150, total_price_usd = 150 * 1.25 = 187.5
    $response = $this->postJson(route('suppliers.purchases.store'), $this->purchasePayload([
        'currency_rate' => 1.25,
        'items'         => [
            ['item_id' => $this->item1->id, 'price' => 100.00, 'quantity' => 2, 'discount_amount' => 50.00],
        ],
    ]))->assertCreated();

    $purchase     = Purchase::find($response->json('data.id'));
    $purchaseItem = $purchase->purchaseItems->first();

    expect((float) $purchaseItem->total_price)->toBe(150.0)
        ->and((float) $purchaseItem->total_price_usd)->toBe(187.5);
});

// ─── Weighted average with non-trivial rate ───────────────────────────────────

it('calculates weighted average using USD cost with non-1.0 rate', function () {
    // EUR @ 2.0 (multiply): price=50, qty=10 → total_price_usd=1000 → cost_per_item_usd=100
    $this->createPurchaseViaApi([
        'currency_rate' => 2.0,
        'items'         => [['item_id' => $this->item1->id, 'price' => 50.00, 'quantity' => 10]],
    ]);

    $itemPrice = ItemPrice::where('item_id', $this->item1->id)->first();
    expect((float) $itemPrice->price_usd)->toBe(100.0);

    // Second purchase EUR @ 2.0: price=100, qty=10 → cost_per_item_usd=200
    // Weighted avg = (10*100 + 10*200) / 20 = 150
    $this->createPurchaseViaApi([
        'currency_rate'           => 2.0,
        'supplier_invoice_number' => 'INV-2025-002',
        'items'                   => [['item_id' => $this->item1->id, 'price' => 100.00, 'quantity' => 10]],
    ]);

    $itemPrice->refresh();
    expect((float) $itemPrice->price_usd)->toBe(150.0);
});
