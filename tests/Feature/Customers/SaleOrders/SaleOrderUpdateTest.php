<?php

use Tests\Feature\Customers\SaleOrders\Concerns\HasSaleOrderSetup;

uses(HasSaleOrderSetup::class);

beforeEach(function () {
    $this->setUpSaleOrders();
    $this->actingAs($this->salesman, 'sanctum');
});

it('can update a pending sale order', function () {
    $sale = $this->createPendingSaleOrder();

    $this->putJson(route('customers.sale-orders.update', $sale), $this->saleOrderPayload([
        'total'     => 2000.00,
        'total_usd' => 2000.00,
        'note'      => 'Updated sale note',
    ]))->assertOk()
       ->assertJson(['data' => ['total' => '2000.00', 'total_usd' => '2000.00', 'note' => 'Updated sale note']]);
});

it('cannot update an approved sale order', function () {
    $sale = $this->createApprovedSaleOrder();

    $this->putJson(route('customers.sale-orders.update', $sale), $this->saleOrderPayload([
        'total' => 2000.00,
        'note'  => 'Trying to update approved sale',
    ]))->assertUnprocessable()
       ->assertJson(['message' => 'Cannot update approved sales']);
});
