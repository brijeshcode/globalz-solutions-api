<?php

use Tests\Feature\Customers\SaleOrders\Concerns\HasSaleOrderSetup;

uses(HasSaleOrderSetup::class);

beforeEach(function () {
    $this->setUpSaleOrders();
    $this->actingAs($this->salesman, 'sanctum');
});

it('shows a sale order with all relationships loaded', function () {
    $sale = $this->createPendingSaleOrder();

    $this->getJson(route('customers.sale-orders.show', $sale))
        ->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => [
                'id',
                'sale_code',
                'customer'        => ['id', 'name', 'code'],
                'currency'        => ['id', 'name', 'code', 'symbol'],
                'warehouse'       => ['id', 'name', 'address'],
                'salesperson'     => ['id', 'name'],
                'created_by_user' => ['id', 'name'],
                'updated_by_user' => ['id', 'name'],
            ],
        ]);
});
