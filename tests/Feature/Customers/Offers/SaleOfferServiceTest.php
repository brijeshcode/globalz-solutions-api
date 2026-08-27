<?php

use App\Models\Items\Item;
use App\Models\Items\ItemOffer;
use App\Services\Customers\SaleOfferService;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->service = new SaleOfferService();
    $this->main    = Item::factory()->create();
    $this->free    = Item::factory()->create();
});

function offerLines(int $offerId, int $mainItem, int $freeItem, int $mainQty, int $freeQty): array
{
    return [
        ['item_id' => $mainItem, 'quantity' => $mainQty, 'price' => 100, 'item_offer_id' => $offerId, 'offer_role' => 'main'],
        ['item_id' => $freeItem, 'quantity' => $freeQty, 'price' => 50,  'item_offer_id' => $offerId, 'offer_role' => 'free', 'discount_percent' => 0],
    ];
}

it('returns non-offer items unchanged', function () {
    $items = [['item_id' => $this->main->id, 'quantity' => 3, 'price' => 100]];
    expect($this->service->normalize($items))->toBe($items);
});

it('forces 100% discount on the free line for a valid offer', function () {
    $offer = ItemOffer::factory()->create([
        'item_id' => $this->main->id, 'free_item_id' => $this->free->id,
        'minimum_quantity' => 5, 'free_quantity' => 1, 'can_change_quantity' => false,
    ]);

    $out = $this->service->normalize(offerLines($offer->id, $this->main->id, $this->free->id, 5, 1));

    expect($out[1]['discount_percent'])->toBe(100);
});

it('rejects an unavailable (expired) offer', function () {
    $offer = ItemOffer::factory()->expired()->create([
        'item_id' => $this->main->id, 'free_item_id' => $this->free->id,
        'minimum_quantity' => 5, 'free_quantity' => 1,
    ]);

    $this->service->normalize(offerLines($offer->id, $this->main->id, $this->free->id, 5, 1));
})->throws(ValidationException::class);

it('rejects a main quantity that is not the minimum when quantity is locked', function () {
    $offer = ItemOffer::factory()->create([
        'item_id' => $this->main->id, 'free_item_id' => $this->free->id,
        'minimum_quantity' => 5, 'free_quantity' => 1, 'can_change_quantity' => false,
    ]);

    $this->service->normalize(offerLines($offer->id, $this->main->id, $this->free->id, 7, 1));
})->throws(ValidationException::class);

it('accepts a multiple and scales the free quantity when quantity can change', function () {
    $offer = ItemOffer::factory()->create([
        'item_id' => $this->main->id, 'free_item_id' => $this->free->id,
        'minimum_quantity' => 5, 'free_quantity' => 2, 'can_change_quantity' => true,
    ]);

    $out = $this->service->normalize(offerLines($offer->id, $this->main->id, $this->free->id, 15, 6));
    expect($out[1]['discount_percent'])->toBe(100);
});

it('rejects a free quantity that does not match the ratio', function () {
    $offer = ItemOffer::factory()->create([
        'item_id' => $this->main->id, 'free_item_id' => $this->free->id,
        'minimum_quantity' => 5, 'free_quantity' => 2, 'can_change_quantity' => true,
    ]);

    $this->service->normalize(offerLines($offer->id, $this->main->id, $this->free->id, 15, 4));
})->throws(ValidationException::class);

it('rejects a second application when allow_multiple is false', function () {
    $offer = ItemOffer::factory()->create([
        'item_id' => $this->main->id, 'free_item_id' => $this->free->id,
        'minimum_quantity' => 5, 'free_quantity' => 1, 'can_change_quantity' => false, 'allow_multiple' => false,
    ]);

    $items = array_merge(
        offerLines($offer->id, $this->main->id, $this->free->id, 5, 1),
        offerLines($offer->id, $this->main->id, $this->free->id, 5, 1),
    );

    $this->service->normalize($items);
})->throws(ValidationException::class);

it('accepts two applications when allow_multiple is true', function () {
    $offer = ItemOffer::factory()->create([
        'item_id' => $this->main->id, 'free_item_id' => $this->free->id,
        'minimum_quantity' => 5, 'free_quantity' => 1, 'can_change_quantity' => false, 'allow_multiple' => true,
    ]);

    $items = array_merge(
        offerLines($offer->id, $this->main->id, $this->free->id, 5, 1),
        offerLines($offer->id, $this->main->id, $this->free->id, 5, 1),
    );

    $out = $this->service->normalize($items);
    expect($out[1]['discount_percent'])->toBe(100)
        ->and($out[3]['discount_percent'])->toBe(100);
});
