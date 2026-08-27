<?php

use App\Models\Items\ItemOffer;
use Tests\Feature\Items\ItemOffers\Concerns\HasItemOfferSetup;

uses(HasItemOfferSetup::class);

beforeEach(fn () => $this->setUpItemOffers());

it('soft deletes an item offer', function () {
    $offer = ItemOffer::factory()->create([
        'item_id'      => $this->item->id,
        'free_item_id' => $this->freeItem->id,
    ]);

    $this->deleteJson(route('items.offers.destroy', $offer))->assertOk();

    $this->assertSoftDeleted('item_offers', ['id' => $offer->id]);
});

it('lists trashed offers', function () {
    $offer = ItemOffer::factory()->create([
        'item_id'      => $this->item->id,
        'free_item_id' => $this->freeItem->id,
    ]);
    $offer->delete();

    $this->getJson(route('items.offers.trashed'))
        ->assertOk()
        ->assertJsonStructure(['message', 'data', 'pagination'])
        ->assertJsonCount(1, 'data');
});

it('restores a trashed offer', function () {
    $offer = ItemOffer::factory()->create([
        'item_id'      => $this->item->id,
        'free_item_id' => $this->freeItem->id,
    ]);
    $offer->delete();

    $this->patchJson(route('items.offers.restore', $offer->id))->assertOk();

    $this->assertDatabaseHas('item_offers', ['id' => $offer->id, 'deleted_at' => null]);
});

it('force deletes a trashed offer', function () {
    $offer = ItemOffer::factory()->create([
        'item_id'      => $this->item->id,
        'free_item_id' => $this->freeItem->id,
    ]);
    $offer->delete();

    $this->deleteJson(route('items.offers.force-delete', $offer->id))->assertOk();

    $this->assertDatabaseMissing('item_offers', ['id' => $offer->id]);
});

it('forbids salesman from deleting an offer', function () {
    $offer = ItemOffer::factory()->create([
        'item_id'      => $this->item->id,
        'free_item_id' => $this->freeItem->id,
    ]);

    $this->actingAs($this->salesman, 'sanctum');

    $this->deleteJson(route('items.offers.destroy', $offer))->assertForbidden();
});
