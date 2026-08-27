<?php

use App\Models\Items\Item;
use App\Models\Items\ItemOffer;
use Tests\Feature\Items\ItemOffers\Concerns\HasItemOfferSetup;

uses(HasItemOfferSetup::class);

beforeEach(fn () => $this->setUpItemOffers());

it('updates an item offer', function () {
    $offer = ItemOffer::factory()->create([
        'item_id'          => $this->item->id,
        'free_item_id'     => $this->freeItem->id,
        'minimum_quantity' => 3,
        'usage_limit'      => 50,
    ]);

    $this->putJson(route('items.offers.update', $offer), $this->offerPayload([
        'minimum_quantity' => 10,
        'usage_limit'      => 200,
    ]))
        ->assertOk()
        ->assertJson(['data' => ['minimum_quantity' => 10, 'usage_limit' => 200]]);

    $this->assertDatabaseHas('item_offers', [
        'id'               => $offer->id,
        'minimum_quantity' => 10,
        'usage_limit'      => 200,
    ]);
});

it('supports partial update with only changed fields', function () {
    $offer = ItemOffer::factory()->create([
        'item_id'      => $this->item->id,
        'free_item_id' => $this->freeItem->id,
        'free_quantity' => 2,
    ]);

    $this->putJson(route('items.offers.update', $offer), ['free_quantity' => 5])
        ->assertOk()
        ->assertJson(['data' => ['free_quantity' => 5]]);
});

it('updates the item reference', function () {
    $newItem = Item::factory()->create();
    $offer   = ItemOffer::factory()->create([
        'item_id'      => $this->item->id,
        'free_item_id' => $this->freeItem->id,
    ]);

    $this->putJson(route('items.offers.update', $offer), $this->offerPayload(['item_id' => $newItem->id]))
        ->assertOk()
        ->assertJson(['data' => ['item_id' => $newItem->id]]);
});

it('sets updated_by automatically', function () {
    $offer = ItemOffer::factory()->create([
        'item_id'      => $this->item->id,
        'free_item_id' => $this->freeItem->id,
    ]);

    $this->putJson(route('items.offers.update', $offer), $this->offerPayload())->assertOk();

    expect($offer->fresh()->updated_by)->toBe($this->admin->id);
});

it('validates item_id must exist on update', function () {
    $offer = ItemOffer::factory()->create([
        'item_id'      => $this->item->id,
        'free_item_id' => $this->freeItem->id,
    ]);

    $this->putJson(route('items.offers.update', $offer), ['item_id' => 99999])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['item_id']);
});

it('validates validity_date must be on or after date on update', function () {
    $offer = ItemOffer::factory()->create([
        'item_id'      => $this->item->id,
        'free_item_id' => $this->freeItem->id,
    ]);

    $this->putJson(route('items.offers.update', $offer), $this->offerPayload([
        'date'          => '2026-06-01',
        'validity_date' => '2026-05-01',
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['validity_date']);
});

it('forbids salesman from updating an offer', function () {
    $offer = ItemOffer::factory()->create([
        'item_id'      => $this->item->id,
        'free_item_id' => $this->freeItem->id,
    ]);

    $this->actingAs($this->salesman, 'sanctum');

    $this->putJson(route('items.offers.update', $offer), $this->offerPayload())
        ->assertForbidden();
});
