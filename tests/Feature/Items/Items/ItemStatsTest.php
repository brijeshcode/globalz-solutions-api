<?php

use Tests\Feature\Items\Items\Concerns\HasItemSetup;

uses(HasItemSetup::class);

beforeEach(function () {
    $this->setUpItems();
});

it('returns item statistics with correct structure and values', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->createItem(['is_active' => true]);
    }
    for ($i = 0; $i < 2; $i++) {
        $this->createItem(['is_active' => false]);
    }

    $stats = $this->getJson(route('setups.items.stats'))
        ->assertOk()
        ->assertJsonStructure(['message', 'data' => ['total_items', 'active_items', 'inactive_items', 'trashed_items', 'low_stock_items', 'total_inventory_value', 'items_by_type', 'items_by_family', 'cost_calculation_breakdown']])
        ->json('data');

    expect($stats['total_items'])->toBe(7)
        ->and($stats['active_items'])->toBe(5)
        ->and($stats['inactive_items'])->toBe(2);
});
