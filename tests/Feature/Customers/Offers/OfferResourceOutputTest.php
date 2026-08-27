<?php

use App\Models\Customers\Sale;
use App\Models\Items\ItemOffer;
use Tests\Feature\Customers\Sales\Concerns\HasSaleSetup;

uses(HasSaleSetup::class);

beforeEach(function () {
    $this->setUpSales();
});

it('exposes offer identity on sale items and has_offers on the sale show response', function () {
    $offer = ItemOffer::factory()->create([
        'item_id' => $this->item1->id, 'free_item_id' => $this->item2->id,
        'minimum_quantity' => 2, 'free_quantity' => 1, 'can_change_quantity' => false,
        'used_count' => 0, 'usage_limit' => 100,
    ]);

    $payload = $this->salePayload([
        'items' => [
            ['item_id' => $this->item1->id, 'quantity' => 2, 'price' => 100.00, 'total_price' => 200.00,
             'item_offer_id' => $offer->id, 'offer_role' => 'main'],
            ['item_id' => $this->item2->id, 'quantity' => 1, 'price' => 150.00, 'discount_percent' => 100,
             'total_price' => 0.00, 'item_offer_id' => $offer->id, 'offer_role' => 'free'],
        ],
    ]);
    $this->postJson(route('customers.sales.store'), $payload)->assertCreated();
    $sale = Sale::orderByDesc('id')->first();

    $this->getJson(route('customers.sales.show', $sale->id))
        ->assertOk()
        ->assertJsonPath('data.has_offers', true)
        ->assertJsonPath('data.sale_items.0.item_offer_id', $offer->id);
});

it('reports has_offers false for a sale without offers', function () {
    $plain = $this->createSaleViaApi();

    $this->getJson(route('customers.sales.show', $plain->id))
        ->assertOk()
        ->assertJsonPath('data.has_offers', false);
});
