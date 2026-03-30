<?php

use App\Models\Customers\Sale;
use App\Services\Inventory\InventoryService;
use Tests\Feature\Customers\Sales\Concerns\HasSaleSetup;

uses(HasSaleSetup::class);

beforeEach(function () {
    $this->setUpSales();
});

it('automatically sets cost price from item price', function () {
    $sale = $this->createSaleViaApi([
        'items' => [['item_id' => $this->item1->id, 'price' => 100.00, 'quantity' => 2, 'total_price' => 200.00]],
    ]);

    expect((float) $sale->saleItems->first()->cost_price)->toBe(45.00);
});

it('calculates unit profit correctly', function () {
    $sale = $this->createSaleViaApi([
        'items' => [['item_id' => $this->item1->id, 'price' => 100.00, 'quantity' => 1, 'total_price' => 100.00]],
    ]);

    // unit profit = selling price - cost price = 100.00 - 45.00 = 55.00
    expect((float) $sale->saleItems->first()->unit_profit)->toBe(55.00);
});

it('calculates total profit per item correctly', function () {
    $sale = $this->createSaleViaApi([
        'items' => [['item_id' => $this->item1->id, 'price' => 100.00, 'quantity' => 3, 'total_price' => 300.00]],
    ]);

    // total profit = unit profit × quantity = 55.00 × 3 = 165.00
    expect((float) $sale->saleItems->first()->total_profit)->toBe(165.00);
});

it('calculates total sale profit across multiple items', function () {
    $sale = $this->createSaleViaApi([
        'total'     => 520.00,
        'total_usd' => 416.00,
        'items'     => [
            ['item_id' => $this->item1->id, 'price' => 100.00, 'quantity' => 2, 'total_price' => 200.00], // profit: 55×2=110
            ['item_id' => $this->item2->id, 'price' => 160.00, 'quantity' => 2, 'total_price' => 320.00], // profit: 90×2=180
        ],
    ]);

    // total profit = 110 + 180 = 290.00
    expect((float) $sale->total_profit)->toBe(290.00);
});

it('includes profit fields in show response', function () {
    $sale = $this->createSaleViaApi([
        'items' => [['item_id' => $this->item1->id, 'price' => 100.00, 'quantity' => 2, 'total_price' => 200.00]],
    ]);

    $data = $this->getJson(route('customers.sales.show', $sale))
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                'total_profit',
                'items' => ['*' => ['cost_price', 'unit_profit', 'total_profit']],
            ],
        ])
        ->json('data');

    expect((float) $data['total_profit'])->toBe(110.00)
        ->and((float) $data['items'][0]['cost_price'])->toBe(45.00)
        ->and((float) $data['items'][0]['unit_profit'])->toBe(55.00)
        ->and((float) $data['items'][0]['total_profit'])->toBe(110.00);
});

it('handles zero cost price correctly', function () {
    $item = Item::factory()->create(['code' => 'ITEM003']);
    \App\Models\Inventory\ItemPrice::where('item_id', $item->id)->delete();
    InventoryService::set($item->id, $this->warehouse->id, 10, 'Test');

    $sale = $this->createSaleViaApi([
        'items' => [['item_id' => $item->id, 'price' => 100.00, 'quantity' => 1, 'total_price' => 100.00]],
    ]);

    $saleItem = $sale->saleItems->first();
    expect((float) $saleItem->cost_price)->toBe(0.00)
        ->and((float) $saleItem->unit_profit)->toBe(100.00)
        ->and((float) $saleItem->total_profit)->toBe(100.00)
        ->and((float) $sale->total_profit)->toBe(100.00);
});
