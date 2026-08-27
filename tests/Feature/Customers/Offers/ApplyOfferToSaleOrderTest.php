<?php

use App\Models\Customers\Sale;
use App\Models\Inventory\Inventory;
use App\Models\Inventory\ItemPrice;
use App\Models\Items\Item;
use App\Models\Items\ItemOffer;
use Tests\Feature\Customers\SaleOrders\Concerns\HasSaleOrderSetup;

uses(HasSaleOrderSetup::class);

beforeEach(function () {
    $this->setUpSaleOrders();
    $this->actingAs($this->salesman, 'sanctum');

    $this->freeItem = Item::factory()->create([
        'is_active' => true, 'created_by' => $this->admin->id, 'updated_by' => $this->admin->id,
    ]);
    ItemPrice::updateOrCreate(['item_id' => $this->freeItem->id], ['price_usd' => 30.00, 'effective_date' => now()]);
    Inventory::create(['item_id' => $this->freeItem->id, 'warehouse_id' => $this->warehouse->id, 'quantity' => 500]);

    $this->offer = ItemOffer::factory()->create([
        'item_id' => $this->item->id, 'free_item_id' => $this->freeItem->id,
        'minimum_quantity' => 5, 'free_quantity' => 1, 'can_change_quantity' => false,
        'allow_multiple' => false, 'used_count' => 0, 'usage_limit' => 100,
    ]);

    $this->offerItems = [
        ['item_id' => $this->item->id, 'quantity' => 5, 'price' => 100.00, 'total_price' => 500.00,
         'item_offer_id' => $this->offer->id, 'offer_role' => 'main'],
        ['item_id' => $this->freeItem->id, 'quantity' => 1, 'price' => 50.00, 'discount_percent' => 100,
         'total_price' => 0.00, 'item_offer_id' => $this->offer->id, 'offer_role' => 'free'],
    ];
});

it('persists a main and free line with offer identity', function () {
    $payload = $this->saleOrderPayload(['items' => $this->offerItems]);
    $this->postJson(route('customers.sale-orders.store'), $payload)->assertCreated();

    $sale = Sale::latest()->first();
    $lines = $sale->items()->get();

    $main = $lines->firstWhere('offer_role', 'main');
    $free = $lines->firstWhere('offer_role', 'free');

    expect($main->item_offer_id)->toBe($this->offer->id)
        ->and($main->quantity)->toBe(5)
        ->and($free->item_offer_id)->toBe($this->offer->id)
        ->and($free->net_sell_price + 0)->toBe(0.0)
        ->and($free->total_profit + 0)->toBeLessThan(0.0); // giveaway cost counts against profit
});

it('rejects applying an expired offer', function () {
    $this->offer->update(['validity_date' => '2020-01-01']);

    $payload = $this->saleOrderPayload(['items' => $this->offerItems]);
    $this->postJson(route('customers.sale-orders.store'), $payload)
        ->assertUnprocessable();
});

it('rejects a second application when allow_multiple is false', function () {
    $payload = $this->saleOrderPayload([
        'items' => array_merge($this->offerItems, $this->offerItems),
    ]);

    $this->postJson(route('customers.sale-orders.store'), $payload)->assertUnprocessable();
});

it('increments the offer used_count when the order is created', function () {
    $payload = $this->saleOrderPayload(['items' => $this->offerItems]);
    $this->postJson(route('customers.sale-orders.store'), $payload)->assertCreated();
    expect($this->offer->fresh()->used_count)->toBe(1);
});
