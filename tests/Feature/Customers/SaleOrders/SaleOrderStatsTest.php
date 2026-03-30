<?php

use Tests\Feature\Customers\SaleOrders\Concerns\HasSaleOrderSetup;

uses(HasSaleOrderSetup::class);

beforeEach(function () {
    $this->setUpSaleOrders();
    $this->actingAs($this->salesman, 'sanctum');
});

it('returns correct sale order statistics', function () {
    for ($i = 0; $i < 3; $i++) {
        $this->createPendingSaleOrder();
    }
    for ($i = 0; $i < 2; $i++) {
        $this->createApprovedSaleOrder();
    }

    $trashed = $this->createPendingSaleOrder();
    $trashed->delete();

    $stats = $this->getJson(route('customers.sale-orders.stats'))
        ->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => [
                'total_sales',
                'pending_sales',
                'approved_sales',
                'trashed_sales',
                'total_amount',
                'total_amount_usd',
                'total_profit',
                'sales_by_prefix',
                'sales_by_currency',
                'recent_approved',
            ],
        ])
        ->json('data');

    expect($stats['total_sales'])->toBe(5)
        ->and($stats['pending_sales'])->toBe(3)
        ->and($stats['approved_sales'])->toBe(2)
        ->and($stats['trashed_sales'])->toBe(1);
});
