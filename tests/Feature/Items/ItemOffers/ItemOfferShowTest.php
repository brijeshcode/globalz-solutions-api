<?php

use App\Models\Items\ItemOffer;
use Tests\Feature\Items\ItemOffers\Concerns\HasItemOfferSetup;

uses(HasItemOfferSetup::class);

beforeEach(fn () => $this->setUpItemOffers());

it('returns an item offer with full structure', function () {
    $offer = ItemOffer::factory()->create([
        'item_id'      => $this->item->id,
        'free_item_id' => $this->freeItem->id,
    ]);

    $this->getJson(route('items.offers.show', $offer))
        ->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => [
                'id', 'item_id', 'item', 'free_item_id', 'free_item',
                'date', 'validity_date', 'minimum_quantity', 'free_quantity',
                'usage_limit', 'used_count', 'can_change_quantity', 'allow_multiple',
                'is_active', 'is_expired', 'is_usage_limit_reached', 'is_available',
                'created_by', 'updated_by', 'created_at', 'updated_at',
            ],
        ])
        ->assertJson(['data' => ['id' => $offer->id]]);
});

it('returns 404 for non-existent offer', function () {
    $this->getJson(route('items.offers.show', 99999))->assertNotFound();
});
