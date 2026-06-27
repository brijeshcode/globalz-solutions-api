<?php

use App\Models\Inventory\ItemPrice;
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

it('recalculates weighted average from scratch when older purchase price is updated', function () {
    // P1: 10 @ $10 → price = $10, inventory = 10
    $purchase1     = $this->createPurchaseViaApi([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item1->id, 'price' => 10.00, 'quantity' => 10]],
    ]);
    $purchaseItem1 = $purchase1->purchaseItems->first();

    // P2: 10 @ $20 → WA = (10*10 + 10*20)/20 = $15, inventory = 20
    $this->createPurchaseViaApi([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item1->id, 'price' => 20.00, 'quantity' => 10]],
    ]);

    $price = ItemPrice::where('item_id', $this->item1->id)->first();
    expect((float) $price->price_usd)->toBe(15.0);

    // Update P1 price from $10 → $15 (P2 still $20, inventory = 20)
    // recalculateFromPurchaseHistory: otherPurchases=[P2:10@$20], inventoryWithoutP1 = 20-10=10
    // baseValue = 200, totalValue = 200 + 10*15 = 350, totalQty = 20 → $17.5
    $this->putJson(route('suppliers.purchases.update', $purchase1), [
        'currency_rate' => 1.0,
        'items'         => [['id' => $purchaseItem1->id, 'item_id' => $this->item1->id, 'price' => 15.00, 'quantity' => 10]],
    ])->assertOk();

    $price->refresh();
    expect((float) $price->price_usd)->toBe(17.5);
});

it('recalculates weighted average when older purchase price is updated with expense', function () {
    // P1: 10 @ $10, expense $20 → cost_per_item = $12, inventory = 10
    $purchase1     = $this->createPurchaseViaApi([
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
    $purchaseItem1    = $purchase1->purchaseItems->first();
    $purchaseExpense1 = $purchase1->purchaseExpenses->first();

    // P2: 10 @ $20 → WA = (10*12 + 10*20)/20 = $16, inventory = 20
    $this->createPurchaseViaApi([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item1->id, 'price' => 20.00, 'quantity' => 10]],
    ]);

    // Update P1 expense from $20 → $30 → new cost_per_item for P1 = (100+30)/10 = $13
    // recalculateFromPurchaseHistory for P1 (qty=10, new cost=$13):
    // otherPurchases = [P2: 10@$20], inventoryWithoutP1 = 20-10=10
    // baseValue = 200, totalValue = 200 + 10*13 = 330, totalQty = 20 → $16.5
    $this->putJson(route('suppliers.purchases.update', $purchase1), [
        'currency_rate' => 1.0,
        'items'         => [['id' => $purchaseItem1->id, 'item_id' => $this->item1->id, 'price' => 10.00, 'quantity' => 10]],
        'expenses'      => [[
            'id'                     => $purchaseExpense1->id,
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
    expect((float) $price->price_usd)->toBe(16.5);
});

// ─── Last Cost ─────────────────────────────────────────────────────────────────

it('does not change last cost price when older purchase price is updated', function () {
    // P1: 10 @ $10 (older) → price = $10
    $purchase1     = $this->createPurchaseViaApi([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item2->id, 'price' => 10.00, 'quantity' => 10]],
    ]);
    $purchaseItem1 = $purchase1->purchaseItems->first();

    // P2: 10 @ $20 (newer) → last cost = $20
    $this->createPurchaseViaApi([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item2->id, 'price' => 20.00, 'quantity' => 10]],
    ]);

    $price = ItemPrice::where('item_id', $this->item2->id)->first();
    expect((float) $price->price_usd)->toBe(20.0);

    // Update P1 price from $10 → $15. P2 is still newer → is_current=false for P1
    // item_price should stay at $20
    $this->putJson(route('suppliers.purchases.update', $purchase1), [
        'currency_rate' => 1.0,
        'items'         => [['id' => $purchaseItem1->id, 'item_id' => $this->item2->id, 'price' => 15.00, 'quantity' => 10]],
    ])->assertOk();

    $price->refresh();
    expect((float) $price->price_usd)->toBe(20.0);
});

it('updates last cost price when newer purchase price is changed', function () {
    // P1: 10 @ $10 (older)
    $this->createPurchaseViaApi([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item2->id, 'price' => 10.00, 'quantity' => 10]],
    ]);

    // P2: 10 @ $20 (newer) → last cost = $20
    $purchase2     = $this->createPurchaseViaApi([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item2->id, 'price' => 20.00, 'quantity' => 10]],
    ]);
    $purchaseItem2 = $purchase2->purchaseItems->first();

    // Update P2 price from $20 → $25. P2 is still newest → is_current=true
    $this->putJson(route('suppliers.purchases.update', $purchase2), [
        'currency_rate' => 1.0,
        'items'         => [['id' => $purchaseItem2->id, 'item_id' => $this->item2->id, 'price' => 25.00, 'quantity' => 10]],
    ])->assertOk();

    $price = ItemPrice::where('item_id', $this->item2->id)->first();
    expect((float) $price->price_usd)->toBe(25.0);
});

it('updates last cost price when expense is added to newer purchase', function () {
    // P1: 10 @ $10 (older)
    $this->createPurchaseViaApi([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item2->id, 'price' => 10.00, 'quantity' => 10]],
    ]);

    // P2: 10 @ $20 (newer) → last cost = $20
    $purchase2     = $this->createPurchaseViaApi([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item2->id, 'price' => 20.00, 'quantity' => 10]],
    ]);
    $purchaseItem2 = $purchase2->purchaseItems->first();

    // Add expense $20 to P2 → cost_per_item = (200+20)/10 = $22 → last cost = $22
    $this->putJson(route('suppliers.purchases.update', $purchase2), [
        'currency_rate' => 1.0,
        'items'         => [['id' => $purchaseItem2->id, 'item_id' => $this->item2->id, 'price' => 20.00, 'quantity' => 10]],
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
    expect((float) $price->price_usd)->toBe(22.0);
});
