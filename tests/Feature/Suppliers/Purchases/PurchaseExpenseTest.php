<?php

use App\Models\Expenses\ExpensePayment;
use App\Models\Expenses\ExpenseTransaction;
use App\Models\Setups\Expenses\ExpenseCategory;
use App\Models\Suppliers\PurchaseExpense;
use App\Services\Suppliers\PurchaseExpenseService;
use Tests\Feature\Suppliers\Purchases\Concerns\HasPurchaseSetup;

uses(HasPurchaseSetup::class);

beforeEach(function () {
    $this->setUpPurchases();
    $this->service = app(PurchaseExpenseService::class);

    // Ensure system category exists
    $this->parent = ExpenseCategory::firstOrCreate(
        ['name' => 'Purchase Expenses'],
        ['is_active' => true, 'is_system' => true]
    );
    $this->shippingCategory = ExpenseCategory::firstOrCreate(
        ['name' => 'Shipping', 'parent_id' => $this->parent->id],
        ['is_active' => true, 'parent_id' => $this->parent->id]
    );
});

it('creates expense lines and expense transactions for a purchase', function () {
    $purchase = $this->createPurchaseViaApi();

    $expenses = [
        [
            'expense_category_id'    => $this->shippingCategory->id,
            'amount'                 => 50.00,
            'amount_usd'             => 40.00,
            'currency_id'            => $this->currency->id,
            'currency_rate'          => 1.25,
            'exclude_from_item_cost' => false,
            'is_paid'                => false,
        ],
    ];

    $this->service->syncExpenseLines($purchase, $expenses);

    expect(PurchaseExpense::where('purchase_id', $purchase->id)->count())->toBe(1);

    $tx = ExpenseTransaction::whereHas(
        'purchaseExpense', fn ($q) => $q->where('purchase_id', $purchase->id)
    )->first();

    expect($tx)->not->toBeNull()
        ->and($tx->amount_usd)->toEqual('40.00000000')
        ->and($tx->subject)->toContain('Shipping')
        ->and($tx->subject)->toContain($purchase->purchase_code);
});

it('creates an expense payment when is_paid is true', function () {
    $purchase = $this->createPurchaseViaApi();
    $account  = \App\Models\Accounts\Account::factory()->create(['current_balance' => 1000]);

    $expenses = [
        [
            'expense_category_id'    => $this->shippingCategory->id,
            'amount'                 => 50.00,
            'amount_usd'             => 40.00,
            'currency_id'            => $this->currency->id,
            'currency_rate'          => 1.25,
            'exclude_from_item_cost' => false,
            'is_paid'                => true,
            'account_id'             => $account->id,
            'payment_note'           => 'bank transfer',
        ],
    ];

    $this->service->syncExpenseLines($purchase, $expenses);

    expect(ExpensePayment::whereHas(
        'expenseTransaction.purchaseExpense', fn ($q) => $q->where('purchase_id', $purchase->id)
    )->count())->toBe(1);

    // AccountsHelper::removeBalance deducts $payment->amount (local currency = 50.00)
    expect((float) $account->fresh()->current_balance)->toEqual(950.0);
});

it('distributes expenses proportionally to items', function () {
    $purchase = $this->createPurchaseViaApi([
        'items' => [
            ['item_id' => $this->item1->id, 'price' => 100.00, 'quantity' => 1],
            ['item_id' => $this->item2->id, 'price' => 300.00, 'quantity' => 1],
        ],
    ]);

    $expenses = [
        [
            'expense_category_id'    => $this->shippingCategory->id,
            'amount'                 => 100.00,
            'amount_usd'             => 80.00,
            'currency_id'            => $this->currency->id,
            'currency_rate'          => 1.25,
            'exclude_from_item_cost' => false,
            'is_paid'                => false,
        ],
    ];

    $this->service->syncExpenseLines($purchase, $expenses);
    $this->service->recalculateItemCosts($purchase);

    $items = $purchase->fresh()->purchaseItems()->orderBy('total_price_usd')->get();

    // item1 total_price_usd ≈ 80 USD (100 / 1.25), item2 ≈ 240 USD
    // distributable = 80 USD over 320 USD sub_total_usd
    // item1 share = 80 * (80/320) = 20 USD, item2 share = 80 * (240/320) = 60 USD
    expect((float) $items[0]->total_expense_usd)->toEqual(20.0);
    expect((float) $items[1]->total_expense_usd)->toEqual(60.0);
});

it('excludes marked expenses from item cost distribution', function () {
    $purchase = $this->createPurchaseViaApi();

    $customsCategory = ExpenseCategory::firstOrCreate(
        ['name' => 'Customs', 'parent_id' => $this->parent->id],
        ['is_active' => true, 'parent_id' => $this->parent->id]
    );

    $expenses = [
        [
            'expense_category_id'    => $this->shippingCategory->id,
            'amount'                 => 50.00,
            'amount_usd'             => 40.00,
            'currency_id'            => $this->currency->id,
            'currency_rate'          => 1.25,
            'exclude_from_item_cost' => false,
            'is_paid'                => false,
        ],
        [
            'expense_category_id'    => $customsCategory->id,
            'amount'                 => 100.00,
            'amount_usd'             => 80.00,
            'currency_id'            => $this->currency->id,
            'currency_rate'          => 1.25,
            'exclude_from_item_cost' => true,
            'is_paid'                => false,
        ],
    ];

    $this->service->syncExpenseLines($purchase, $expenses);
    $this->service->recalculateItemCosts($purchase);

    $item = $purchase->fresh()->purchaseItems()->first();

    // Only 40 USD distributable (customs excluded), single item gets all of it
    expect((float) $item->total_expense_usd)->toEqual(40.0);
});

it('removes deleted expense lines and restores account balance', function () {
    $purchase = $this->createPurchaseViaApi();
    $account  = \App\Models\Accounts\Account::factory()->create(['current_balance' => 1000]);

    $firstExpenses = [
        [
            'expense_category_id'    => $this->shippingCategory->id,
            'amount'                 => 50.00,
            'amount_usd'             => 40.00,
            'currency_id'            => $this->currency->id,
            'currency_rate'          => 1.25,
            'exclude_from_item_cost' => false,
            'is_paid'                => true,
            'account_id'             => $account->id,
        ],
    ];

    $this->service->syncExpenseLines($purchase, $firstExpenses);

    // Deducts $payment->amount (50.00 local currency)
    expect((float) $account->fresh()->current_balance)->toEqual(950.0);

    // Sync with empty array — removes the line
    $this->service->syncExpenseLines($purchase, []);

    expect(PurchaseExpense::where('purchase_id', $purchase->id)->count())->toBe(0);
    // Payment deletion restores balance via ExpenseTransaction::deleting cascade
    expect((float) $account->fresh()->current_balance)->toEqual(1000.0);
});
