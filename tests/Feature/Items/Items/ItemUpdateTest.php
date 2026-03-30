<?php

use Tests\Feature\Items\Items\Concerns\HasItemSetup;

uses(HasItemSetup::class);

beforeEach(function () {
    $this->setUpItems();
});

it('updates an item', function () {
    $item         = $this->createItem();
    $originalCode = $item->code;

    $this->putJson(route('setups.items.update', $item), [
        'short_name'  => 'Updated Item',
        'description' => 'Updated description',
        'base_cost'   => 200.00,
        'base_sell'   => 250.00,
    ])
        ->assertOk()
        ->assertJson(['data' => ['code' => $originalCode, 'short_name' => 'Updated Item', 'base_cost' => 200.00, 'base_sell' => 250.00]]);

    $this->assertDatabaseHas('items', ['id' => $item->id, 'code' => $originalCode, 'short_name' => 'Updated Item', 'base_cost' => 200.00]);
});

it('allows updating the item code', function () {
    $item = $this->createItem(['code' => '5001']);

    $this->putJson(route('setups.items.update', $item), ['code' => 'NEW-5001', 'short_name' => 'Updated Item'])
        ->assertOk()
        ->assertJson(['data' => ['code' => 'NEW-5001']]);

    $this->assertDatabaseHas('items', ['id' => $item->id, 'code' => 'NEW-5001']);
});
