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
    $this->customsCategory = ExpenseCategory::firstOrCreate(
        ['name' => 'Customs', 'parent_id' => $parent->id],
        ['is_active' => true, 'parent_id' => $parent->id]
    );
});

// ─── Weighted Average ──────────────────────────────────────────────────────────

it('includes expense in item price for weighted average first purchase', function () {
    // 10 @ $10 = $100 total_price_usd, expense $20 → cost_per_item = (100+20)/10 = $12
    $this->createPurchaseViaApi([
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

    $price = ItemPrice::where('item_id', $this->item1->id)->first();
    expect((float) $price->price_usd)->toBe(12.0);
});

it('distributes expense proportionally when two items are in the purchase', function () {
    // item1 (WA):  10 @ $10 = $100  total_price_usd
    // item2 (LC):  10 @ $20 = $200  total_price_usd
    // sub_total = $300, expense = $30
    // item1 share = 30 * (100/300) = $10  → cost_per_item = (100+10)/10 = $11
    // item2 share = 30 * (200/300) = $20  → cost_per_item = (200+20)/10 = $22
    $this->createPurchaseViaApi([
        'currency_rate' => 1.0,
        'items'         => [
            ['item_id' => $this->item1->id, 'price' => 10.00, 'quantity' => 10],
            ['item_id' => $this->item2->id, 'price' => 20.00, 'quantity' => 10],
        ],
        'expenses' => [[
            'expense_category_id'    => $this->shippingCategory->id,
            'amount'                 => 30.00,
            'amount_usd'             => 30.00,
            'currency_id'            => $this->currency->id,
            'currency_rate'          => 1.0,
            'exclude_from_item_cost' => false,
            'is_paid'                => false,
        ]],
    ]);

    expect((float) ItemPrice::where('item_id', $this->item1->id)->value('price_usd'))->toBe(11.0)
        ->and((float) ItemPrice::where('item_id', $this->item2->id)->value('price_usd'))->toBe(22.0);
});

it('does not include excluded expense in item price', function () {
    // Shipping $20 (included) + Customs $30 (excluded)
    // Only $20 distributed → cost_per_item = (100+20)/10 = $12 (not $15 if customs included)
    $this->createPurchaseViaApi([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item1->id, 'price' => 10.00, 'quantity' => 10]],
        'expenses'      => [
            [
                'expense_category_id'    => $this->shippingCategory->id,
                'amount'                 => 20.00,
                'amount_usd'             => 20.00,
                'currency_id'            => $this->currency->id,
                'currency_rate'          => 1.0,
                'exclude_from_item_cost' => false,
                'is_paid'                => false,
            ],
            [
                'expense_category_id'    => $this->customsCategory->id,
                'amount'                 => 30.00,
                'amount_usd'             => 30.00,
                'currency_id'            => $this->currency->id,
                'currency_rate'          => 1.0,
                'exclude_from_item_cost' => true,
                'is_paid'                => false,
            ],
        ],
    ]);

    $price = ItemPrice::where('item_id', $this->item1->id)->first();
    expect((float) $price->price_usd)->toBe(12.0);
});

it('calculates weighted average using expense-adjusted cost from prior purchase', function () {
    // P1: 10 @ $10, expense $20 → cost_per_item = $12, inventory = 10, price = $12
    $this->createPurchaseViaApi([
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

    // P2: 10 @ $20, expense $10 → cost_per_item = (200+10)/10 = $21
    // WA = (10*12 + 10*21) / 20 = (120+210)/20 = $16.5
    $this->createPurchaseViaApi([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item1->id, 'price' => 20.00, 'quantity' => 10]],
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
    expect((float) $price->price_usd)->toBe(16.5);
});

// ─── Last Cost ─────────────────────────────────────────────────────────────────

it('includes expense in item price for last cost first purchase', function () {
    // 10 @ $10 = $100, expense $20 → cost_per_item = $12
    $this->createPurchaseViaApi([
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

    $price = ItemPrice::where('item_id', $this->item2->id)->first();
    expect((float) $price->price_usd)->toBe(12.0);
});

it('uses expense-adjusted last cost from second purchase', function () {
    // P1: 10 @ $10, expense $20 → cost_per_item = $12, price = $12
    $this->createPurchaseViaApi([
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

    // P2: 5 @ $8, expense $10 → cost_per_item = (40+10)/5 = $10
    // Last cost = $10 (newest purchase's cost_per_item_usd)
    $this->createPurchaseViaApi([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item2->id, 'price' => 8.00, 'quantity' => 5]],
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

    $price = ItemPrice::where('item_id', $this->item2->id)->first();
    expect((float) $price->price_usd)->toBe(10.0);
});
