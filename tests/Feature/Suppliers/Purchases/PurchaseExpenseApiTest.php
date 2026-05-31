<?php

use App\Models\Setups\Expenses\ExpenseCategory;
use App\Models\Suppliers\Purchase;
use App\Models\Suppliers\PurchaseExpense;
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

// ─── Store with expenses ───────────────────────────────────────────────────────

it('creates expense lines when expenses are included in store payload', function () {
    $response = $this->postJson(route('suppliers.purchases.store'), $this->purchasePayload([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item1->id, 'price' => 100.00, 'quantity' => 2]],
        'expenses'      => [
            [
                'expense_category_id'    => $this->shippingCategory->id,
                'amount'                 => 50.00,
                'amount_usd'             => 50.00,
                'currency_id'            => $this->currency->id,
                'currency_rate'          => 1.0,
                'exclude_from_item_cost' => false,
                'is_paid'                => false,
            ],
        ],
    ]))->assertCreated();

    $purchase = Purchase::find($response->json('data.id'));

    expect(PurchaseExpense::where('purchase_id', $purchase->id)->count())->toBe(1);
});

it('includes expense total in final_total_usd when expenses are in store payload', function () {
    // items total_usd=200, expense amount_usd=50 → final_total_usd=250
    $response = $this->postJson(route('suppliers.purchases.store'), $this->purchasePayload([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item1->id, 'price' => 100.00, 'quantity' => 2]],
        'expenses'      => [
            [
                'expense_category_id' => $this->shippingCategory->id,
                'amount'              => 50.00,
                'amount_usd'          => 50.00,
                'currency_id'         => $this->currency->id,
                'currency_rate'       => 1.0,
                'is_paid'             => false,
            ],
        ],
    ]))->assertCreated();

    $purchase = Purchase::find($response->json('data.id'));

    expect((float) $purchase->total_usd)->toBe(200.0)
        ->and((float) $purchase->total_expense_usd)->toBe(50.0)
        ->and((float) $purchase->final_total_usd)->toBe(250.0);
});

it('creates multiple expense lines when multiple expenses are in store payload', function () {
    $response = $this->postJson(route('suppliers.purchases.store'), $this->purchasePayload([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item1->id, 'price' => 100.00, 'quantity' => 1]],
        'expenses'      => [
            [
                'expense_category_id' => $this->shippingCategory->id,
                'amount'              => 30.00,
                'amount_usd'          => 30.00,
                'currency_id'         => $this->currency->id,
                'currency_rate'       => 1.0,
                'is_paid'             => false,
            ],
            [
                'expense_category_id' => $this->customsCategory->id,
                'amount'              => 20.00,
                'amount_usd'          => 20.00,
                'currency_id'         => $this->currency->id,
                'currency_rate'       => 1.0,
                'is_paid'             => false,
            ],
        ],
    ]))->assertCreated();

    $purchase = Purchase::find($response->json('data.id'));

    expect(PurchaseExpense::where('purchase_id', $purchase->id)->count())->toBe(2)
        ->and((float) $purchase->total_expense_usd)->toBe(50.0)
        ->and((float) $purchase->final_total_usd)->toBe(150.0);
});

it('final_total_usd equals total_usd when no expenses provided', function () {
    $response = $this->postJson(route('suppliers.purchases.store'), $this->purchasePayload([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item1->id, 'price' => 100.00, 'quantity' => 2]],
    ]))->assertCreated();

    $purchase = Purchase::find($response->json('data.id'));

    expect((float) $purchase->final_total_usd)->toBe(200.0)
        ->and((float) $purchase->total_expense_usd)->toBe(0.0);
});

// ─── Update with expenses ──────────────────────────────────────────────────────

it('adds expense lines when expenses are included in update payload', function () {
    $response = $this->postJson(route('suppliers.purchases.store'), $this->purchasePayload([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item1->id, 'price' => 100.00, 'quantity' => 2]],
    ]))->assertCreated();

    $purchase     = Purchase::find($response->json('data.id'));
    $purchaseItem = $purchase->purchaseItems->first();

    $this->putJson(route('suppliers.purchases.update', $purchase), [
        'items'    => [['id' => $purchaseItem->id, 'item_id' => $this->item1->id, 'price' => 100.00, 'quantity' => 2]],
        'expenses' => [
            [
                'expense_category_id' => $this->shippingCategory->id,
                'amount'              => 60.00,
                'amount_usd'          => 60.00,
                'currency_id'         => $this->currency->id,
                'currency_rate'       => 1.0,
                'is_paid'             => false,
            ],
        ],
    ])->assertOk();

    $purchase->refresh();

    expect(PurchaseExpense::where('purchase_id', $purchase->id)->count())->toBe(1)
        ->and((float) $purchase->total_expense_usd)->toBe(60.0)
        ->and((float) $purchase->final_total_usd)->toBe(260.0);
});

it('removes expense lines when update payload sends empty expenses', function () {
    // Create purchase with an expense
    $response = $this->postJson(route('suppliers.purchases.store'), $this->purchasePayload([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item1->id, 'price' => 100.00, 'quantity' => 2]],
        'expenses'      => [
            [
                'expense_category_id' => $this->shippingCategory->id,
                'amount'              => 50.00,
                'amount_usd'          => 50.00,
                'currency_id'         => $this->currency->id,
                'currency_rate'       => 1.0,
                'is_paid'             => false,
            ],
        ],
    ]))->assertCreated();

    $purchase     = Purchase::find($response->json('data.id'));
    $purchaseItem = $purchase->purchaseItems->first();

    expect(PurchaseExpense::where('purchase_id', $purchase->id)->count())->toBe(1);

    // Update with empty expenses array — should remove the line
    $this->putJson(route('suppliers.purchases.update', $purchase), [
        'items'    => [['id' => $purchaseItem->id, 'item_id' => $this->item1->id, 'price' => 100.00, 'quantity' => 2]],
        'expenses' => [],
    ])->assertOk();

    $purchase->refresh();

    expect(PurchaseExpense::where('purchase_id', $purchase->id)->count())->toBe(0)
        ->and((float) $purchase->total_expense_usd)->toBe(0.0)
        ->and((float) $purchase->final_total_usd)->toBe(200.0);
});

it('validates expense fields are required when expenses array is present', function () {
    $this->postJson(route('suppliers.purchases.store'), $this->purchasePayload([
        'items'    => [['item_id' => $this->item1->id, 'price' => 100.00, 'quantity' => 1]],
        'expenses' => [
            ['exclude_from_item_cost' => false], // missing required fields
        ],
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'expenses.0.expense_category_id',
            'expenses.0.amount',
            'expenses.0.amount_usd',
            'expenses.0.currency_id',
            'expenses.0.currency_rate',
        ]);
});
