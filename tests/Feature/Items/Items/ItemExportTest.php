<?php

use Tests\Feature\Items\Items\Concerns\HasItemSetup;

uses(HasItemSetup::class);

beforeEach(function () {
    $this->setUpItems();
});

it('exports items with correct structure', function () {
    $this->createItem();
    $this->createItem();
    $this->createItem();

    $this->getJson(route('setups.items.export'))
        ->assertOk()
        ->assertJsonStructure(['message', 'data' => ['*' => ['code', 'short_name', 'description', 'item_type', 'item_family', 'item_group', 'item_category', 'item_brand', 'item_unit', 'supplier', 'tax_code', 'base_cost', 'base_sell', 'starting_price', 'starting_quantity', 'low_quantity_alert', 'cost_calculation', 'status']]])
        ->assertJsonCount(3, 'data');
});
