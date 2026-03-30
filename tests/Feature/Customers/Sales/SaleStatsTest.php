<?php

use Tests\Feature\Customers\Sales\Concerns\HasSaleSetup;

uses(HasSaleSetup::class);

beforeEach(function () {
    $this->setUpSales();
});

it('returns correct sale statistics', function () {
    for ($i = 0; $i < 5; $i++) {
        $this->createApprovedSale([
            'prefix'    => 'INV',
            'total'     => 1000.00,
            'total_usd' => 800.00,
        ]);
    }

    $stats = $this->getJson(route('customers.sales.stats'))
        ->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => ['total_sales', 'trashed_sales', 'total_amount', 'total_amount_usd'],
        ])
        ->json('data');

    expect($stats['total_sales'])->toBe(5);
});
