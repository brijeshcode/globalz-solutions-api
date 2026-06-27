<?php

use App\Models\Inventory\Inventory;
use App\Models\Inventory\ItemPrice;
use App\Models\Inventory\ItemPriceHistory;
use App\Models\Setups\Expenses\ExpenseCategory;
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

it('updates weighted average price when only item unit price changes', function () {
    // Single purchase: 10 @ $10 → price = $10
    $purchase     = $this->createPurchaseViaApi([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item1->id, 'price' => 10.00, 'quantity' => 10]],
    ]);
    $purchaseItem = $purchase->purchaseItems->first();

    // Change price to $15 (qty unchanged) → only purchase in stock, so WA = $15
    $this->putJson(route('suppliers.purchases.update', $purchase), [
        'currency_rate' => 1.0,
        'items'         => [['id' => $purchaseItem->id, 'item_id' => $this->item1->id, 'price' => 15.00, 'quantity' => 10]],
    ])->assertOk();

    $price = ItemPrice::where('item_id', $this->item1->id)->first();
    expect((float) $price->price_usd)->toBe(15.0);
});

it('does not update weighted average price when only quantity changes at same unit price', function () {
    // Single purchase: 10 @ $10 → price = $10, cost_per_item_usd = $10
    $purchase     = $this->createPurchaseViaApi([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item1->id, 'price' => 10.00, 'quantity' => 10]],
    ]);
    $purchaseItem = $purchase->purchaseItems->first();

    // Change qty to 20 (price unchanged) → cost_per_item_usd stays $10 → no price recalculation
    $this->putJson(route('suppliers.purchases.update', $purchase), [
        'currency_rate' => 1.0,
        'items'         => [['id' => $purchaseItem->id, 'item_id' => $this->item1->id, 'price' => 10.00, 'quantity' => 20]],
    ])->assertOk();

    $price     = ItemPrice::where('item_id', $this->item1->id)->first();
    $inventory = Inventory::where('item_id', $this->item1->id)->where('warehouse_id', $this->warehouse->id)->first();

    expect((float) $price->price_usd)->toBe(10.0)
        ->and((float) $inventory->quantity)->toBe(20.0);
});

it('updates weighted average price when quantity changes with expense present', function () {
    // 10 @ $10 = $100, expense $20 → cost_per_item = $12
    $purchase     = $this->createPurchaseViaApi([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item1->id, 'price' => 10.00, 'quantity' => 10]],
        'expenses'      => [[
            'expense_category_id'    => $this->shippingCategory->id,
            'amount'                 => 20.00,
            'amount_usd'             => 20.00,
            'currency_id'            => $this->currency->id,
            'currency_rate'          => 1.0,
            'exclude_from_item_cost' => false,
            'is_paid'                => false,
        ]],
    ]);
    $purchaseItem    = $purchase->purchaseItems->first();
    $purchaseExpense = $purchase->purchaseExpenses->first();

    $price = ItemPrice::where('item_id', $this->item1->id)->first();
    expect((float) $price->price_usd)->toBe(12.0);

    // Change qty from 10 → 20 (price unchanged, expense $20 stays)
    // New cost_per_item = (200+20)/20 = $11 → different from $12 → price updates
    $this->putJson(route('suppliers.purchases.update', $purchase), [
        'currency_rate' => 1.0,
        'items'         => [['id' => $purchaseItem->id, 'item_id' => $this->item1->id, 'price' => 10.00, 'quantity' => 20]],
        'expenses'      => [[
            'id'                     => $purchaseExpense->id,
            'expense_category_id'    => $this->shippingCategory->id,
            'amount'                 => 20.00,
            'amount_usd'             => 20.00,
            'currency_id'            => $this->currency->id,
            'currency_rate'          => 1.0,
            'exclude_from_item_cost' => false,
            'is_paid'                => false,
        ]],
    ])->assertOk();

    // New cost_per_item_usd = $11 → price recalculates
    $price->refresh();
    expect((float) $price->price_usd)->not()->toBe(12.0);
});

it('creates a new price history entry when weighted average price changes', function () {
    $purchase     = $this->createPurchaseViaApi([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item1->id, 'price' => 10.00, 'quantity' => 10]],
    ]);
    $purchaseItem = $purchase->purchaseItems->first();

    $historiesBeforeUpdate = ItemPriceHistory::where('item_id', $this->item1->id)->count();

    $this->putJson(route('suppliers.purchases.update', $purchase), [
        'currency_rate' => 1.0,
        'items'         => [['id' => $purchaseItem->id, 'item_id' => $this->item1->id, 'price' => 15.00, 'quantity' => 10]],
    ])->assertOk();

    $historiesAfterUpdate = ItemPriceHistory::where('item_id', $this->item1->id)->count();
    expect($historiesAfterUpdate)->toBe($historiesBeforeUpdate + 1);
});

// ─── Last Cost ─────────────────────────────────────────────────────────────────

it('updates last cost price when only item unit price changes', function () {
    // 10 @ $10 → price = $10
    $purchase     = $this->createPurchaseViaApi([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item2->id, 'price' => 10.00, 'quantity' => 10]],
    ]);
    $purchaseItem = $purchase->purchaseItems->first();

    // Change price to $15 → last cost = $15
    $this->putJson(route('suppliers.purchases.update', $purchase), [
        'currency_rate' => 1.0,
        'items'         => [['id' => $purchaseItem->id, 'item_id' => $this->item2->id, 'price' => 15.00, 'quantity' => 10]],
    ])->assertOk();

    $price = ItemPrice::where('item_id', $this->item2->id)->first();
    expect((float) $price->price_usd)->toBe(15.0);
});

it('does not update last cost price when only quantity changes at same unit price', function () {
    // 10 @ $10 → price = $10, cost_per_item_usd = $10
    $purchase     = $this->createPurchaseViaApi([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item2->id, 'price' => 10.00, 'quantity' => 10]],
    ]);
    $purchaseItem = $purchase->purchaseItems->first();

    // Change qty to 20 (same price) → cost_per_item_usd unchanged → no price recalculation
    $this->putJson(route('suppliers.purchases.update', $purchase), [
        'currency_rate' => 1.0,
        'items'         => [['id' => $purchaseItem->id, 'item_id' => $this->item2->id, 'price' => 10.00, 'quantity' => 20]],
    ])->assertOk();

    $price     = ItemPrice::where('item_id', $this->item2->id)->first();
    $inventory = Inventory::where('item_id', $this->item2->id)->where('warehouse_id', $this->warehouse->id)->first();

    expect((float) $price->price_usd)->toBe(10.0)
        ->and((float) $inventory->quantity)->toBe(20.0);
});
