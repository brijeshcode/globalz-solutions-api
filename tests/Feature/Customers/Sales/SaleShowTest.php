<?php

use Tests\Feature\Customers\Sales\Concerns\HasSaleSetup;

uses(HasSaleSetup::class);

beforeEach(function () {
    $this->setUpSales();
});

it('shows a sale with all relationships', function () {
    $sale = $this->createSaleViaApi();

    $this->getJson(route('customers.sales.show', $sale))
        ->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => [
                'id',
                'code',
                'sale_code',
                'warehouse' => ['id', 'name'],
                'currency'  => ['id', 'name', 'code', 'symbol'],
                'items'     => ['*' => ['id', 'item_code', 'price', 'quantity', 'total_price']],
            ],
        ]);
});

it('returns 404 for a non-existent sale', function () {
    $this->getJson(route('customers.sales.show', 999999))
        ->assertNotFound();
});
