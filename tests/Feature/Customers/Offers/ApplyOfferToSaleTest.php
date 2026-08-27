<?php

use App\Models\Customers\Sale;
use App\Models\Items\ItemOffer;
use Tests\Feature\Customers\Sales\Concerns\HasSaleSetup;

uses(HasSaleSetup::class);

beforeEach(function () {
    $this->setUpSales();
    $this->offer = ItemOffer::factory()->create([
        'item_id' => $this->item1->id, 'free_item_id' => $this->item2->id,
        'minimum_quantity' => 2, 'free_quantity' => 1, 'can_change_quantity' => false,
        'used_count' => 0, 'usage_limit' => 100,
    ]);
});

it('applies an offer through the direct sale endpoint', function () {
    $payload = $this->salePayload([
        'items' => [
            ['item_id' => $this->item1->id, 'quantity' => 2, 'price' => 100.00, 'total_price' => 200.00,
             'item_offer_id' => $this->offer->id, 'offer_role' => 'main'],
            ['item_id' => $this->item2->id, 'quantity' => 1, 'price' => 150.00, 'discount_percent' => 100,
             'total_price' => 0.00, 'item_offer_id' => $this->offer->id, 'offer_role' => 'free'],
        ],
    ]);

    $this->postJson(route('customers.sales.store'), $payload)->assertCreated();

    $sale = Sale::latest()->first();
    $free = $sale->saleItems()->where('offer_role', 'free')->first();

    expect($sale->saleItems()->where('offer_role', 'main')->exists())->toBeTrue()
        ->and($free->net_sell_price + 0)->toBe(0.0)
        ->and($this->offer->fresh()->used_count)->toBe(1);
});
