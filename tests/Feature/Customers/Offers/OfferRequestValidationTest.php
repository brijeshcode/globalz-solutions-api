<?php

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
    ItemPrice::updateOrCreate(
        ['item_id' => $this->freeItem->id],
        ['price_usd' => 30.00, 'effective_date' => now()]
    );
    Inventory::create([
        'item_id' => $this->freeItem->id, 'warehouse_id' => $this->warehouse->id, 'quantity' => 500,
    ]);
    $this->offer = ItemOffer::factory()->create([
        'item_id' => $this->item->id, 'free_item_id' => $this->freeItem->id,
        'minimum_quantity' => 5, 'free_quantity' => 1, 'can_change_quantity' => false,
    ]);
});

it('accepts a zero-priced free offer line without a below-cost error', function () {
    $payload = $this->saleOrderPayload([
        'items' => [
            ['item_id' => $this->item->id, 'quantity' => 5, 'price' => 100.00, 'total_price' => 500.00,
             'item_offer_id' => $this->offer->id, 'offer_role' => 'main'],
            ['item_id' => $this->freeItem->id, 'quantity' => 1, 'price' => 0.00, 'discount_percent' => 100,
             'total_price' => 0.00, 'item_offer_id' => $this->offer->id, 'offer_role' => 'free'],
        ],
    ]);

    $this->postJson(route('customers.sale-orders.store'), $payload)->assertCreated();
});

it('rejects an invalid offer_role', function () {
    $payload = $this->saleOrderPayload([
        'items' => [
            ['item_id' => $this->item->id, 'quantity' => 5, 'price' => 100.00, 'total_price' => 500.00,
             'item_offer_id' => $this->offer->id, 'offer_role' => 'bogus'],
        ],
    ]);

    $this->postJson(route('customers.sale-orders.store'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['items.0.offer_role']);
});
