<?php

use Tests\Feature\Items\ItemAdjusts\Concerns\HasItemAdjustSetup;

uses(HasItemAdjustSetup::class);

beforeEach(function () {
    $this->setUpItemAdjusts();
    $this->actingAsAdmin();
});

it('shows an item adjust with all relationships', function () {
    $this->setupInventory($this->item1->id, 50);
    $adjust = $this->createAdjustViaApi();

    $this->getJson(route('items.adjusts.show', $adjust))
        ->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => [
                'id', 'code', 'type',
                'warehouse' => ['id', 'name'],
                'items'     => ['*' => ['id', 'item' => ['id', 'code', 'short_name'], 'quantity']],
            ],
        ]);
});
