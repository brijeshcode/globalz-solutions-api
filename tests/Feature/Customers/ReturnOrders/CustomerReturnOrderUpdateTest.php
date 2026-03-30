<?php

use Tests\Feature\Customers\ReturnOrders\Concerns\HasCustomerReturnOrderSetup;

uses(HasCustomerReturnOrderSetup::class);

beforeEach(function () {
    $this->setUpCustomerReturnOrders();
    $this->actingAs($this->salesman, 'sanctum');
});

it('can update a pending return order', function () {
    $return = $this->createPendingReturn();

    $this->putJson(route('customers.return-orders.update', $return), $this->returnPayload([
        'total'     => 2000.00,
        'total_usd' => 1600.00,
        'note'      => 'Updated return note',
    ]))->assertOk()
       ->assertJson(['data' => ['total' => '2000.00', 'total_usd' => '1600.00', 'note' => 'Updated return note']]);
});

it('cannot update an approved return order', function () {
    $return = $this->createApprovedReturn();

    $this->putJson(route('customers.return-orders.update', $return), $this->returnPayload([
        'total' => 2000.00,
        'note'  => 'Trying to update approved return',
    ]))->assertUnprocessable()
       ->assertJson(['message' => 'Cannot update approved returns']);
});
