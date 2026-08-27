<?php

use App\Models\Customers\SaleItems;
use App\Models\Items\ItemOffer;
use Tests\Feature\Customers\Sales\Concerns\HasSaleSetup;

uses(HasSaleSetup::class);

beforeEach(function () {
    $this->setUpSales();
    $this->offer = ItemOffer::factory()->create([
        'item_id' => $this->item1->id, 'free_item_id' => $this->item2->id,
        'minimum_quantity' => 5, 'free_quantity' => 1, 'used_count' => 0, 'usage_limit' => 100,
    ]);
    $this->sale = $this->createApprovedSale();
});

it('increments used_count when a main offer line is created', function () {
    SaleItems::create([
        'sale_id' => $this->sale->id, 'item_id' => $this->item1->id, 'item_code' => $this->item1->code,
        'quantity' => 5, 'price' => 100, 'item_offer_id' => $this->offer->id, 'offer_role' => 'main',
    ]);

    expect($this->offer->fresh()->used_count)->toBe(1);
});

it('does not increment used_count for the free line', function () {
    SaleItems::create([
        'sale_id' => $this->sale->id, 'item_id' => $this->item2->id, 'item_code' => $this->item2->code,
        'quantity' => 1, 'price' => 50, 'discount_percent' => 100,
        'item_offer_id' => $this->offer->id, 'offer_role' => 'free',
    ]);

    expect($this->offer->fresh()->used_count)->toBe(0);
});

it('decrements used_count when the main offer line is deleted', function () {
    $line = SaleItems::create([
        'sale_id' => $this->sale->id, 'item_id' => $this->item1->id, 'item_code' => $this->item1->code,
        'quantity' => 5, 'price' => 100, 'item_offer_id' => $this->offer->id, 'offer_role' => 'main',
    ]);
    expect($this->offer->fresh()->used_count)->toBe(1);

    $line->delete();
    expect($this->offer->fresh()->used_count)->toBe(0);
});
