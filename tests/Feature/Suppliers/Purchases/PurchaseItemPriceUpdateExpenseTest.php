<?php

use App\Models\Inventory\ItemPrice;
use App\Models\Setups\Expenses\ExpenseCategory;
use App\Models\Suppliers\Purchase;
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

it('updates weighted average item price when expense is added to delivered purchase', function () {
    // Start: 10 @ $10 (no expense) → price = $10
    $purchase     = $this->createPurchaseViaApi([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item1->id, 'price' => 10.00, 'quantity' => 10]],
    ]);
    $purchaseItem = $purchase->purchaseItems->first();

    $price = ItemPrice::where('item_id', $this->item1->id)->first();
    expect((float) $price->price_usd)->toBe(10.0);

    // Add $20 expense → cost_per_item = (100+20)/10 = $12
    $this->putJson(route('suppliers.purchases.update', $purchase), [
        'currency_rate' => 1.0,
        'items'         => [['id' => $purchaseItem->id, 'item_id' => $this->item1->id, 'price' => 10.00, 'quantity' => 10]],
        'expenses'      => [[
            'expense_category_id'    => $this->shippingCategory->id,
            'amount'                 => 20.00,
            'amount_usd'             => 20.00,
            'currency_id'            => $this->currency->id,
            'currency_rate'          => 1.0,
            'exclude_from_item_cost' => false,
            'is_paid'                => false,
        ]],
    ])->assertOk();

    $price->refresh();
    expect((float) $price->price_usd)->toBe(12.0);
});

it('updates weighted average item price when expense is removed from delivered purchase', function () {
    // Start: 10 @ $10, expense $20 → price = $12
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
    $purchaseItem = $purchase->purchaseItems->first();

    $price = ItemPrice::where('item_id', $this->item1->id)->first();
    expect((float) $price->price_usd)->toBe(12.0);

    // Remove expense → cost_per_item reverts to $10
    $this->putJson(route('suppliers.purchases.update', $purchase), [
        'currency_rate' => 1.0,
        'items'         => [['id' => $purchaseItem->id, 'item_id' => $this->item1->id, 'price' => 10.00, 'quantity' => 10]],
        'expenses'      => [],
    ])->assertOk();

    $price->refresh();
    expect((float) $price->price_usd)->toBe(10.0);
});

it('updates weighted average item price when expense amount changes', function () {
    // Start: 10 @ $10, expense $20 → price = $12
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

    // Change expense from $20 to $30 → cost_per_item = (100+30)/10 = $13
    $this->putJson(route('suppliers.purchases.update', $purchase), [
        'currency_rate' => 1.0,
        'items'         => [['id' => $purchaseItem->id, 'item_id' => $this->item1->id, 'price' => 10.00, 'quantity' => 10]],
        'expenses'      => [[
            'id'                     => $purchaseExpense->id,
            'expense_category_id'    => $this->shippingCategory->id,
            'amount'                 => 30.00,
            'amount_usd'             => 30.00,
            'currency_id'            => $this->currency->id,
            'currency_rate'          => 1.0,
            'exclude_from_item_cost' => false,
            'is_paid'                => false,
        ]],
    ])->assertOk();

    $price = ItemPrice::where('item_id', $this->item1->id)->first();
    expect((float) $price->price_usd)->toBe(13.0);
});

// ─── Last Cost ─────────────────────────────────────────────────────────────────

it('updates last cost item price when expense is added to delivered purchase', function () {
    // Start: 10 @ $10 (no expense) → price = $10
    $purchase     = $this->createPurchaseViaApi([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item2->id, 'price' => 10.00, 'quantity' => 10]],
    ]);
    $purchaseItem = $purchase->purchaseItems->first();

    // Add $20 expense → cost_per_item = (100+20)/10 = $12
    $this->putJson(route('suppliers.purchases.update', $purchase), [
        'currency_rate' => 1.0,
        'items'         => [['id' => $purchaseItem->id, 'item_id' => $this->item2->id, 'price' => 10.00, 'quantity' => 10]],
        'expenses'      => [[
            'expense_category_id'    => $this->shippingCategory->id,
            'amount'                 => 20.00,
            'amount_usd'             => 20.00,
            'currency_id'            => $this->currency->id,
            'currency_rate'          => 1.0,
            'exclude_from_item_cost' => false,
            'is_paid'                => false,
        ]],
    ])->assertOk();

    $price = ItemPrice::where('item_id', $this->item2->id)->first();
    expect((float) $price->price_usd)->toBe(12.0);
});

it('updates last cost item price when expense is removed from delivered purchase', function () {
    // Start: 10 @ $10, expense $20 → price = $12
    $purchase     = $this->createPurchaseViaApi([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item2->id, 'price' => 10.00, 'quantity' => 10]],
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
    $purchaseItem = $purchase->purchaseItems->first();

    $price = ItemPrice::where('item_id', $this->item2->id)->first();
    expect((float) $price->price_usd)->toBe(12.0);

    // Remove expense → cost_per_item reverts to $10
    $this->putJson(route('suppliers.purchases.update', $purchase), [
        'currency_rate' => 1.0,
        'items'         => [['id' => $purchaseItem->id, 'item_id' => $this->item2->id, 'price' => 10.00, 'quantity' => 10]],
        'expenses'      => [],
    ])->assertOk();

    $price->refresh();
    expect((float) $price->price_usd)->toBe(10.0);
});
