<?php

use App\Models\Inventory\ItemPrice;
use App\Models\Setups\Expenses\ExpenseCategory;
use App\Services\Suppliers\PurchaseService;
use Tests\Feature\Suppliers\Purchases\Concerns\HasPurchaseSetup;

uses(HasPurchaseSetup::class);

uses()->group('api', 'suppliers', 'purchases');

beforeEach(function () {
    $this->setUpPurchases();

    $parent = ExpenseCategory::firstOrCreate(
        ['name' => 'Purchase Expenses'],
        ['is_active' => true, 'is_system' => true]
    );
    $this->shippingCategory = ExpenseCategory::firstOrCreate(
        ['name' => 'Shipping', 'parent_id' => $parent->id],
        ['is_active' => true, 'parent_id' => $parent->id]
    );
});

// ─── Weighted Average ──────────────────────────────────────────────────────────

it('resets weighted average price to zero when delivered purchase is deleted', function () {
    $purchase = $this->createPurchaseViaApi([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item1->id, 'price' => 10.00, 'quantity' => 10]],
    ]);

    $price = ItemPrice::where('item_id', $this->item1->id)->first();
    expect((float) $price->price_usd)->toBe(10.0);

    app(PurchaseService::class)->deletePurchase($purchase);

    $price->refresh();
    expect((float) $price->price_usd)->toBe(0.0);
});

it('sets weighted average price from re-added item at different price after purchase is deleted', function () {
    // P1: 10 @ $10 → price = $10
    $purchase1 = $this->createPurchaseViaApi([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item1->id, 'price' => 10.00, 'quantity' => 10]],
    ]);

    // Delete P1 → price falls to 0, inventory = 0
    app(PurchaseService::class)->deletePurchase($purchase1);

    // P2: 10 @ $15 → inventory was 0, so WA = $15
    $this->createPurchaseViaApi([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item1->id, 'price' => 15.00, 'quantity' => 10]],
    ]);

    $price = ItemPrice::where('item_id', $this->item1->id)->first();
    expect((float) $price->price_usd)->toBe(15.0);
});

it('includes expense in weighted average price after purchase is deleted and item re-added', function () {
    // P1: 10 @ $10 → price = $10
    $purchase1 = $this->createPurchaseViaApi([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item1->id, 'price' => 10.00, 'quantity' => 10]],
    ]);

    // Delete P1 → price = 0, inventory = 0
    app(PurchaseService::class)->deletePurchase($purchase1);

    // P2: 10 @ $15, expense $10 → cost_per_item = (150+10)/10 = $16
    // inventory before delivery = 0 → price = $16
    $this->createPurchaseViaApi([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item1->id, 'price' => 15.00, 'quantity' => 10]],
        'expenses'      => [[
            'expense_category_id'    => $this->shippingCategory->id,
            'amount'                 => 10.00,
            'amount_usd'             => 10.00,
            'currency_id'            => $this->currency->id,
            'currency_rate'          => 1.0,
            'exclude_from_item_cost' => false,
            'is_paid'                => false,
        ]],
    ]);

    $price = ItemPrice::where('item_id', $this->item1->id)->first();
    expect((float) $price->price_usd)->toBe(16.0);
});

// ─── Last Cost ─────────────────────────────────────────────────────────────────

it('resets last cost price to zero when delivered purchase is deleted', function () {
    $purchase = $this->createPurchaseViaApi([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item2->id, 'price' => 10.00, 'quantity' => 10]],
    ]);

    $price = ItemPrice::where('item_id', $this->item2->id)->first();
    expect((float) $price->price_usd)->toBe(10.0);

    app(PurchaseService::class)->deletePurchase($purchase);

    $price->refresh();
    expect((float) $price->price_usd)->toBe(0.0);
});

it('sets last cost price from re-added item at different price after purchase is deleted', function () {
    // P1: 10 @ $10 → price = $10
    $purchase1 = $this->createPurchaseViaApi([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item2->id, 'price' => 10.00, 'quantity' => 10]],
    ]);

    // Delete P1 → price = 0
    app(PurchaseService::class)->deletePurchase($purchase1);

    // P2: 5 @ $20 → last cost = $20
    $this->createPurchaseViaApi([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item2->id, 'price' => 20.00, 'quantity' => 5]],
    ]);

    $price = ItemPrice::where('item_id', $this->item2->id)->first();
    expect((float) $price->price_usd)->toBe(20.0);
});
