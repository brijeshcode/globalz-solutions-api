<?php

use App\Jobs\RecalculateSaleProfitForPurchaseJob;
use App\Models\Customers\Customer;
use App\Models\Customers\Sale;
use App\Models\Customers\SaleItems;
use App\Models\Inventory\ItemPriceHistory;
use App\Models\Items\Item;
use App\Models\Setting;
use App\Models\Setups\Expenses\ExpenseCategory;
use App\Models\Suppliers\Purchase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    $this->setUpPurchases();

    Setting::updateOrCreate(
        ['group_name' => 'sales', 'key_name' => 'code_counter'],
        ['value' => '1000', 'data_type' => 'number', 'description' => 'Sale code counter']
    );

    $this->customer = Customer::factory()->create(['is_active' => true]);

    $parent = ExpenseCategory::firstOrCreate(
        ['name' => 'Purchase Expenses'],
        ['is_active' => true, 'is_system' => true]
    );
    $this->shippingCategory = ExpenseCategory::firstOrCreate(
        ['name' => 'Shipping', 'parent_id' => $parent->id],
        ['is_active' => true, 'parent_id' => $parent->id]
    );

    // Creates a delivered purchase for one item at USD prices (currency_rate 1.0)
    $this->makeDeliveredPurchase = function (Item $item, float $price, int $qty, ?string $deliveredDate = null): Purchase {
        $purchase = $this->createPurchaseViaApi([
            'currency_rate' => 1.0,
            'items'         => [['item_id' => $item->id, 'price' => $price, 'quantity' => $qty]],
        ]);

        if ($deliveredDate) {
            $purchase->updateQuietly(['delivered_at' => $deliveredDate . ' 00:00:00']);
        }

        return $purchase->fresh();
    };

    $this->makeSale = function (Item $item, float $price, int $qty, ?string $date = null): Sale {
        $total = $price * $qty;

        $this->postJson(route('customers.sales.store'), [
            'date'          => $date ?? now()->toDateString(),
            'prefix'        => 'INV',
            'warehouse_id'  => $this->warehouse->id,
            'currency_id'   => $this->currency->id,
            'customer_id'   => $this->customer->id,
            'currency_rate' => 1.0,
            'sub_total'     => $total,
            'sub_total_usd' => $total,
            'total'         => $total,
            'total_usd'     => $total,
            'items'         => [[
                'item_id'     => $item->id,
                'price'       => $price,
                'quantity'    => $qty,
                'total_price' => $total,
            ]],
        ])->assertCreated();

        return Sale::latest('id')->first();
    };

    $this->addExpense = function (Purchase $purchase, float $amountUsd): void {
        $purchaseItem = $purchase->purchaseItems()->first();

        $this->putJson(route('suppliers.purchases.update', $purchase), [
            'items'    => [[
                'id'       => $purchaseItem->id,
                'item_id'  => $purchaseItem->item_id,
                'price'    => (float) $purchaseItem->price,
                'quantity' => (int) $purchaseItem->quantity,
            ]],
            'expenses' => [[
                'expense_category_id'    => $this->shippingCategory->id,
                'amount'                 => $amountUsd,
                'amount_usd'             => $amountUsd,
                'currency_id'            => $this->currency->id,
                'currency_rate'          => 1.0,
                'exclude_from_item_cost' => false,
                'is_paid'                => false,
            ]],
        ])->assertOk();
    };

    // Newest purchase-sourced history row for a purchase's (single) item
    $this->latestPurchaseRow = function (Purchase $purchase): ItemPriceHistory {
        return ItemPriceHistory::where('source_type', 'purchase_item')
            ->whereIn('source_id', $purchase->purchaseItems()->pluck('id'))
            ->orderByDesc('id')
            ->firstOrFail();
    };
});

// ─── Creation stamping ─────────────────────────────────────────────────────────

it('stamps cost_history_id from the current price row when a sale is created', function () {
    $purchase = ($this->makeDeliveredPurchase)($this->item2, 100.00, 5);
    $row      = ($this->latestPurchaseRow)($purchase);

    $sale     = ($this->makeSale)($this->item2, 150.00, 2);
    $saleItem = SaleItems::where('sale_id', $sale->id)->firstOrFail();

    expect((float) $saleItem->cost_price)->toEqualWithDelta(100.0, 0.0001)
        ->and($saleItem->cost_history_id)->toBe($row->id);
});

// ─── Preview ───────────────────────────────────────────────────────────────────

it('previews profit changes after an expense is added to a delivered purchase', function () {
    Queue::fake();

    $purchase = ($this->makeDeliveredPurchase)($this->item2, 100.00, 5);
    $sale     = ($this->makeSale)($this->item2, 150.00, 2);

    ($this->addExpense)($purchase, 50.00); // (500 + 50) / 5 → 110 per unit

    Queue::assertPushed(RecalculateSaleProfitForPurchaseJob::class);

    $response = $this->getJson(route('suppliers.purchases.recalculate-sale-profit.preview', $purchase))
        ->assertOk()
        ->assertJsonStructure(['message', 'data' => ['summary', 'sales']]);

    $summary = $response->json('data.summary');
    expect($summary['sale_items_to_update'])->toBe(1)
        ->and($summary['sales_affected'])->toBe(1)
        ->and($summary['total_profit_change'])->toEqualWithDelta(-20.0, 0.0001);

    $salePreview = $response->json('data.sales.0');
    expect($salePreview['sale_id'])->toBe($sale->id)
        ->and($salePreview['sale_code'])->toBe($sale->prefix . $sale->code)
        ->and($salePreview['old_total_profit'])->toEqualWithDelta(100.0, 0.0001)
        ->and($salePreview['new_total_profit'])->toEqualWithDelta(80.0, 0.0001)
        ->and($salePreview['profit_change'])->toEqualWithDelta(-20.0, 0.0001);

    $itemPreview = $response->json('data.sales.0.items.0');
    expect($itemPreview['item_code'])->toBe('ITEM002')
        ->and($itemPreview['item_name'])->toBe('Test Item 2')
        ->and($itemPreview['old_cost'])->toEqualWithDelta(100.0, 0.0001)
        ->and($itemPreview['new_cost'])->toEqualWithDelta(110.0, 0.0001);
});

it('returns an empty preview when everything is already up to date', function () {
    $purchase = ($this->makeDeliveredPurchase)($this->item2, 100.00, 5);
    ($this->makeSale)($this->item2, 150.00, 2);

    $response = $this->getJson(route('suppliers.purchases.recalculate-sale-profit.preview', $purchase))
        ->assertOk();

    expect($response->json('data.summary.sale_items_to_update'))->toBe(0)
        ->and($response->json('data.sales'))->toBe([]);
});

// ─── Execute ───────────────────────────────────────────────────────────────────

it('applies the recalculation and stamps the new history row on execute', function () {
    Queue::fake();

    $purchase = ($this->makeDeliveredPurchase)($this->item2, 100.00, 5);
    $sale     = ($this->makeSale)($this->item2, 150.00, 2);

    ($this->addExpense)($purchase, 50.00);

    $response = $this->postJson(route('suppliers.purchases.recalculate-sale-profit', $purchase))
        ->assertOk();

    expect($response->json('data.updated_sale_items'))->toBe(1)
        ->and($response->json('data.updated_sales'))->toBe(1);

    $row      = ($this->latestPurchaseRow)($purchase);
    $saleItem = SaleItems::where('sale_id', $sale->id)->firstOrFail();

    expect((float) $saleItem->cost_price)->toEqualWithDelta(110.0, 0.0001)
        ->and($saleItem->cost_history_id)->toBe($row->id)
        ->and((float) $saleItem->unit_profit)->toEqualWithDelta(40.0, 0.0001)
        ->and((float) $saleItem->total_profit)->toEqualWithDelta(80.0, 0.0001)
        ->and((float) $sale->fresh()->total_profit)->toEqualWithDelta(80.0, 0.0001);
});

it('is idempotent — a second execute finds nothing to change', function () {
    Queue::fake();

    $purchase = ($this->makeDeliveredPurchase)($this->item2, 100.00, 5);
    ($this->makeSale)($this->item2, 150.00, 2);

    ($this->addExpense)($purchase, 50.00);

    $this->postJson(route('suppliers.purchases.recalculate-sale-profit', $purchase))->assertOk();

    $second = $this->postJson(route('suppliers.purchases.recalculate-sale-profit', $purchase))->assertOk();

    expect($second->json('data.updated_sale_items'))->toBe(0);
});

it('rejects preview and execute for a purchase that is not delivered', function () {
    $response = $this->postJson(route('suppliers.purchases.store'), $this->purchasePayload([
        'currency_rate' => 1.0,
        'items'         => [['item_id' => $this->item2->id, 'price' => 100.00, 'quantity' => 5]],
    ]))->assertCreated();

    $purchase = Purchase::findOrFail($response->json('data.id'));

    $this->getJson(route('suppliers.purchases.recalculate-sale-profit.preview', $purchase))
        ->assertUnprocessable();
    $this->postJson(route('suppliers.purchases.recalculate-sale-profit', $purchase))
        ->assertUnprocessable();
});

// ─── Auto-trigger ──────────────────────────────────────────────────────────────

it('automatically recalculates sale profit when an expense is added', function () {
    $purchase = ($this->makeDeliveredPurchase)($this->item2, 100.00, 5);
    $sale     = ($this->makeSale)($this->item2, 150.00, 2);

    // Sync queue in tests: the auto-dispatched job runs inline with the update
    ($this->addExpense)($purchase, 50.00);

    $saleItem = SaleItems::where('sale_id', $sale->id)->firstOrFail();

    expect((float) $saleItem->cost_price)->toEqualWithDelta(110.0, 0.0001)
        ->and((float) $sale->fresh()->total_profit)->toEqualWithDelta(80.0, 0.0001);
});

// ─── Pointer rule ──────────────────────────────────────────────────────────────

it('keeps each sale bound to its own purchase — corrections never leak to other purchases sales', function () {
    $p1 = ($this->makeDeliveredPurchase)($this->item2, 100.00, 5);
    $s1 = ($this->makeSale)($this->item2, 150.00, 1); // stamped to P1's row

    $p2 = ($this->makeDeliveredPurchase)($this->item2, 120.00, 5);
    $s2 = ($this->makeSale)($this->item2, 150.00, 1); // stamped to P2's row

    ($this->addExpense)($p1, 50.00); // P1 cost 100 → 110; sync queue recalculates inline

    $s1Item = SaleItems::where('sale_id', $s1->id)->firstOrFail();
    $s2Item = SaleItems::where('sale_id', $s2->id)->firstOrFail();

    expect((float) $s1Item->cost_price)->toEqualWithDelta(110.0, 0.0001)
        ->and($s1Item->cost_history_id)->toBe(($this->latestPurchaseRow)($p1)->id)
        ->and((float) $s2Item->cost_price)->toEqualWithDelta(120.0, 0.0001)
        ->and($s2Item->cost_history_id)->toBe(($this->latestPurchaseRow)($p2)->id);
});

// ─── Window backfill for unstamped (legacy) sales ─────────────────────────────

it('backfills unstamped sales by delivery window and stamps them', function () {
    $p1 = ($this->makeDeliveredPurchase)($this->item2, 100.00, 5, '2025-01-10');
    $p2 = ($this->makeDeliveredPurchase)($this->item2, 120.00, 5, '2025-02-10');

    $s1 = ($this->makeSale)($this->item2, 150.00, 1, '2025-01-20');
    $s2 = ($this->makeSale)($this->item2, 150.00, 1, '2025-02-15');

    // Simulate legacy rows: no stamp, wrong cost
    DB::table('sale_items')->whereIn('sale_id', [$s1->id, $s2->id])
        ->update(['cost_history_id' => null, 'cost_price' => 50]);

    $response = $this->postJson(route('suppliers.purchases.recalculate-sale-profit', $p1))
        ->assertOk();

    expect($response->json('data.updated_sale_items'))->toBe(2);

    $s1Item = SaleItems::where('sale_id', $s1->id)->firstOrFail();
    $s2Item = SaleItems::where('sale_id', $s2->id)->firstOrFail();

    expect((float) $s1Item->cost_price)->toEqualWithDelta(100.0, 0.0001)
        ->and($s1Item->cost_history_id)->toBe(($this->latestPurchaseRow)($p1)->id)
        ->and((float) $s2Item->cost_price)->toEqualWithDelta(120.0, 0.0001)
        ->and($s2Item->cost_history_id)->toBe(($this->latestPurchaseRow)($p2)->id);
});

// ─── Manual price stamps are respected ─────────────────────────────────────────

it('leaves sales stamped with a manual price row untouched', function () {
    $purchase = ($this->makeDeliveredPurchase)($this->item2, 100.00, 5);
    $sale     = ($this->makeSale)($this->item2, 150.00, 1);

    $manualRow = ItemPriceHistory::create([
        'item_id'                => $this->item2->id,
        'price_usd'              => 999,
        'average_weighted_price' => 999,
        'latest_price'           => 0,
        'effective_date'         => now()->toDateString(),
        'source_type'            => 'manual',
        'source_id'              => null,
        'note'                   => 'Manual correction',
        'is_current'             => false,
    ]);

    DB::table('sale_items')->where('sale_id', $sale->id)
        ->update(['cost_history_id' => $manualRow->id, 'cost_price' => 999]);

    $response = $this->postJson(route('suppliers.purchases.recalculate-sale-profit', $purchase))
        ->assertOk();

    expect($response->json('data.updated_sale_items'))->toBe(0);

    $saleItem = SaleItems::where('sale_id', $sale->id)->firstOrFail();
    expect((float) $saleItem->cost_price)->toEqualWithDelta(999.0, 0.0001)
        ->and($saleItem->cost_history_id)->toBe($manualRow->id);
});

// ─── Weighted average ──────────────────────────────────────────────────────────

it('uses the recomputed weighted average when an expense lands on a weighted-average item', function () {
    Queue::fake();

    $purchase = ($this->makeDeliveredPurchase)($this->item1, 100.00, 2); // weighted-average item
    ($this->makeSale)($this->item1, 150.00, 1);

    ($this->addExpense)($purchase, 22.00); // (200 + 22) / 2 = 111

    $response = $this->getJson(route('suppliers.purchases.recalculate-sale-profit.preview', $purchase))
        ->assertOk();

    expect($response->json('data.sales.0.items.0.new_cost'))->toEqualWithDelta(111.0, 0.0001)
        ->and($response->json('data.summary.total_profit_change'))->toEqualWithDelta(-11.0, 0.0001);
});
