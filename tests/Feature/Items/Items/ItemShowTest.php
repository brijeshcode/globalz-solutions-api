<?php

use Tests\Feature\Items\Items\Concerns\HasItemSetup;

uses(HasItemSetup::class);

beforeEach(function () {
    $this->setUpItems();
});

it('shows an item', function () {
    $item = $this->createItem();

    $this->getJson(route('setups.items.show', $item))
        ->assertOk()
        ->assertJson(['data' => ['id' => $item->id, 'code' => $item->code, 'short_name' => $item->short_name]]);
});

it('returns 404 for a non-existent item', function () {
    $this->getJson(route('setups.items.show', 999))->assertNotFound();
});
