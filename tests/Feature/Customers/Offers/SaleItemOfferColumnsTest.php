<?php

use App\Models\Customers\SaleItems;
use App\Models\Items\ItemOffer;
use Tests\Feature\Customers\Sales\Concerns\HasSaleSetup;

uses(HasSaleSetup::class);

beforeEach(function () {
    $this->setUpSales();
});

it('persists and reads offer identity columns on a sale item', function () {
    $offer = ItemOffer::factory()->create([
        'item_id'      => $this->item1->id,
        'free_item_id' => $this->item2->id,
    ]);

    $sale = $this->createApprovedSale();

    $line = SaleItems::create([
        'sale_id'       => $sale->id,
        'item_id'       => $this->item1->id,
        'item_code'     => $this->item1->code,
        'quantity'      => 5,
        'price'         => 100,
        'item_offer_id' => $offer->id,
        'offer_role'    => 'main',
    ]);

    $fresh = $line->fresh();
    expect($fresh->item_offer_id)->toBe($offer->id)
        ->and($fresh->offer_role)->toBe('main')
        ->and($fresh->itemOffer->id)->toBe($offer->id);
});
