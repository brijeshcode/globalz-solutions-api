<?php

use Tests\Feature\Items\ItemTransfers\Concerns\HasItemTransferSetup;

uses(HasItemTransferSetup::class);

beforeEach(function () {
    $this->setUpItemTransfers();
});

it('shows an item transfer with all relationships', function () {
    $this->setupInventory($this->item1->id, $this->fromWarehouse->id, 50);
    $transfer = $this->createTransferViaApi();

    $this->getJson(route('items.transfers.show', $transfer))
        ->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => [
                'id', 'code',
                'from_warehouse' => ['id', 'name'],
                'to_warehouse'   => ['id', 'name'],
                'items'          => ['*' => ['id', 'item' => ['id', 'code', 'short_name'], 'quantity']],
            ],
        ]);
});
