<?php

use App\Models\Items\ItemAdjust;
use Tests\Feature\Items\ItemAdjusts\Concerns\HasItemAdjustSetup;

uses(HasItemAdjustSetup::class);

beforeEach(function () {
    $this->setUpItemAdjusts();
    $this->actingAsAdmin();
});

it('returns adjustment statistics with correct values', function () {
    ItemAdjust::factory()->add()->create(['warehouse_id' => $this->warehouse->id]);
    ItemAdjust::factory()->add()->create(['warehouse_id' => $this->warehouse->id]);
    ItemAdjust::factory()->subtract()->create(['warehouse_id' => $this->warehouse->id]);

    $stats = $this->getJson(route('items.adjusts.stats'))
        ->assertOk()
        ->assertJsonStructure(['message', 'data' => ['total_adjustments', 'total_add_adjustments', 'total_subtract_adjustments']])
        ->json('data');

    expect($stats['total_adjustments'])->toBe(3)
        ->and($stats['total_add_adjustments'])->toBe(2)
        ->and($stats['total_subtract_adjustments'])->toBe(1);
});
