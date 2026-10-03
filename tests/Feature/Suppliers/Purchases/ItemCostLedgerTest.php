<?php

use App\Models\Customers\Customer;
use App\Models\Inventory\ItemPrice;
use App\Models\Items\Item;
use App\Models\Setting;
use App\Services\Inventory\ItemCostLedger;
use App\Services\Inventory\PriceService;
use Tests\Feature\Suppliers\Purchases\Concerns\HasPurchaseSetup;

uses(HasPurchaseSetup::class);
uses()->group('api', 'inventory', 'price');

beforeEach(function () {
    $this->setUpPurchases();

    Setting::updateOrCreate(
        ['group_name' => 'sales', 'key_name' => 'code_counter'],
        ['value' => '1000', 'data_type' => 'number', 'description' => 'Sale code counter']
    );

    $this->customer = Customer::factory()->create(['is_active' => true]);

    $this->sell = function (Item $item, float $price, int $qty, string $date) {
        $total = $price * $qty;
        $this->postJson(route('customers.sales.store'), [
            'date'          => $date,
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
    };

    // The ledger sequences purchases by DELIVERY date, so pin delivered_at to the
    // same date we pass (createPurchaseViaApi otherwise stamps delivered_at = now()).
    $this->buy = function (Item $item, float $price, int $qty, string $date) {
        $purchase = $this->createPurchaseViaApi([
            'currency_rate' => 1.0,
            'date'          => $date,
            'items'         => [['item_id' => $item->id, 'price' => $price, 'quantity' => $qty]],
        ]);
        $purchase->updateQuietly(['delivered_at' => $date . ' 00:00:00']);

        return $purchase->fresh();
    };
});

// This is the whole bug in one test: the old audit averaged every unit ever bought
// (100@10 + 100@20)/200 = 15.00 and flagged the item as wrong. The real moving
// average, which respects the 90 units sold in between, is 19.09 — and that is
// exactly what the write path already stores.
it('replays a global moving average that accounts for sales between purchases', function () {
    ($this->buy)($this->item1, 10.0, 100, '2025-01-01'); // avg 10, stock 100
    ($this->sell)($this->item1, 15.0, 90, '2025-01-05');  // stock 10
    ($this->buy)($this->item1, 20.0, 100, '2025-01-10'); // (10*10 + 100*20)/110 = 19.09

    $replay = ItemCostLedger::replay($this->item1->id);

    expect($replay['price'])->toEqualWithDelta(19.09, 0.01)
        ->and($replay['quantity'])->toEqualWithDelta(110, 0.01);

    // Write path stores the moving average too, so the audit must now agree — no false positive.
    expect(ItemCostLedger::diagnose($this->item1->id)['status'])->toBe('ok');

    $stored = (float) ItemPrice::where('item_id', $this->item1->id)->value('price_usd');
    expect($stored)->toEqualWithDelta(19.09, 0.01);
});

it('publishes the latest purchase cost for last-cost items while keeping the average underneath', function () {
    ($this->buy)($this->item2, 10.0, 100, '2025-01-01');
    ($this->buy)($this->item2, 20.0, 50, '2025-01-10');

    $replay = ItemCostLedger::replay($this->item2->id);

    expect($replay['price'])->toEqualWithDelta(20.0, 0.01)           // last cost is published
        ->and($replay['average'])->toEqualWithDelta(13.3333, 0.01);  // moving average still maintained
});

it('recalculates through the ledger when the cost method is switched both ways', function () {
    ($this->buy)($this->item1, 10.0, 100, '2025-01-01');
    ($this->buy)($this->item1, 20.0, 50, '2025-01-10'); // avg 13.3333, last cost 20

    $price = fn() => (float) ItemPrice::where('item_id', $this->item1->id)->value('price_usd');

    expect($price())->toEqualWithDelta(13.3333, 0.01); // starts weighted_average

    // weighted_average -> last_cost: publish the latest purchase cost
    $this->item1->update(['cost_calculation' => Item::COST_LAST_COST]);
    PriceService::updateFromCalculationTypeChange($this->item1, Item::COST_WEIGHTED_AVERAGE, Item::COST_LAST_COST);
    expect($price())->toEqualWithDelta(20.0, 0.01);

    // last_cost -> weighted_average: the average was kept underneath, so it returns
    $this->item1->update(['cost_calculation' => Item::COST_WEIGHTED_AVERAGE]);
    PriceService::updateFromCalculationTypeChange($this->item1, Item::COST_LAST_COST, Item::COST_WEIGHTED_AVERAGE);
    expect($price())->toEqualWithDelta(13.3333, 0.01);
});
