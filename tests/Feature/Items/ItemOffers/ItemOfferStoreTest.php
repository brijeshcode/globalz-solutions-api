<?php

use App\Models\Items\ItemOffer;
use Tests\Feature\Items\ItemOffers\Concerns\HasItemOfferSetup;

uses(HasItemOfferSetup::class);

beforeEach(fn () => $this->setUpItemOffers());

it('creates an item offer with required fields', function () {
    $this->postJson(route('items.offers.store'), $this->offerPayload())
        ->assertCreated()
        ->assertJsonStructure([
            'message',
            'data' => [
                'id', 'item_id', 'free_item_id', 'date', 'validity_date',
                'minimum_quantity', 'free_quantity', 'usage_limit',
                'can_change_quantity', 'allow_multiple', 'is_active',
                'is_expired', 'is_available', 'used_count',
            ],
        ]);

    $this->assertDatabaseHas('item_offers', [
        'item_id'          => $this->item->id,
        'free_item_id'     => $this->freeItem->id,
        'minimum_quantity' => 5,
        'free_quantity'    => 1,
        'usage_limit'      => 100,
    ]);
});

it('creates an offer with all optional fields', function () {
    $payload = $this->offerPayload([
        'can_change_quantity' => true,
        'allow_multiple'      => true,
        'is_active'           => false,
    ]);

    $this->postJson(route('items.offers.store'), $payload)
        ->assertCreated()
        ->assertJson(['data' => [
            'can_change_quantity' => true,
            'allow_multiple'      => true,
            'is_active'           => false,
        ]]);
});

it('sets created_by and updated_by automatically', function () {
    $this->postJson(route('items.offers.store'), $this->offerPayload())->assertCreated();

    $offer = ItemOffer::latest('id')->first();
    expect($offer->created_by)->toBe($this->admin->id)
        ->and($offer->updated_by)->toBe($this->admin->id);
});

it('initialises used_count to zero', function () {
    $this->postJson(route('items.offers.store'), $this->offerPayload())->assertCreated();

    expect(ItemOffer::latest('id')->first()->used_count)->toBe(0);
});

it('validates required fields', function () {
    $this->postJson(route('items.offers.store'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'item_id', 'free_item_id', 'date', 'validity_date',
            'minimum_quantity', 'free_quantity', 'usage_limit',
        ]);
});

it('validates item_id must exist', function () {
    $this->postJson(route('items.offers.store'), $this->offerPayload(['item_id' => 99999]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['item_id']);
});

it('validates free_item_id must exist', function () {
    $this->postJson(route('items.offers.store'), $this->offerPayload(['free_item_id' => 99999]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['free_item_id']);
});

it('validates validity_date must be on or after date', function () {
    $this->postJson(route('items.offers.store'), $this->offerPayload([
        'date'          => '2026-06-01',
        'validity_date' => '2026-05-01',
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['validity_date']);
});

it('accepts validity_date equal to date', function () {
    $this->postJson(route('items.offers.store'), $this->offerPayload([
        'date'          => '2026-06-01',
        'validity_date' => '2026-06-01',
    ]))->assertCreated();
});

it('validates minimum_quantity, free_quantity, usage_limit must be at least 1', function (string $field) {
    $this->postJson(route('items.offers.store'), $this->offerPayload([$field => 0]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors([$field]);
})->with(['minimum_quantity', 'free_quantity', 'usage_limit']);

it('forbids salesman from creating an offer', function () {
    $this->actingAs($this->salesman, 'sanctum');

    $this->postJson(route('items.offers.store'), $this->offerPayload())
        ->assertForbidden();
});
