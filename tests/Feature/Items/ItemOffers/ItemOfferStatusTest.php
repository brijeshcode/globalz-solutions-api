<?php

use App\Models\Items\ItemOffer;
use Tests\Feature\Items\ItemOffers\Concerns\HasItemOfferSetup;

uses(HasItemOfferSetup::class);

beforeEach(fn () => $this->setUpItemOffers());

it('activates an inactive offer', function () {
    $offer = ItemOffer::factory()->inactive()->create([
        'item_id'      => $this->item->id,
        'free_item_id' => $this->freeItem->id,
    ]);

    $this->patchJson(route('items.offers.status', $offer), ['status' => true])->assertOk();

    expect($offer->fresh()->is_active)->toBeTrue();
});

it('deactivates an active offer', function () {
    $offer = ItemOffer::factory()->create([
        'item_id'      => $this->item->id,
        'free_item_id' => $this->freeItem->id,
        'is_active'    => true,
    ]);

    $this->patchJson(route('items.offers.status', $offer), ['status' => false])->assertOk();

    expect($offer->fresh()->is_active)->toBeFalse();
});

it('forbids salesman from changing offer status', function () {
    $offer = ItemOffer::factory()->create([
        'item_id'      => $this->item->id,
        'free_item_id' => $this->freeItem->id,
    ]);

    $this->actingAs($this->salesman, 'sanctum');

    $this->patchJson(route('items.offers.status', $offer), ['status' => false])
        ->assertForbidden();
});
