<?php

use Tests\Feature\Customers\ReturnOrders\Concerns\HasCustomerReturnOrderSetup;

uses(HasCustomerReturnOrderSetup::class);

beforeEach(function () {
    $this->setUpCustomerReturnOrders();
    $this->actingAs($this->salesman, 'sanctum');
});

it('shows a return order with all relationships loaded', function () {
    $return = $this->createPendingReturn();

    $this->getJson(route('customers.return-orders.show', $return))
        ->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => [
                'id',
                'return_code',
                'customer'       => ['id', 'name', 'code'],
                'currency'       => ['id', 'name', 'code', 'symbol'],
                'warehouse'      => ['id', 'name', 'address'],
                'salesperson'    => ['id', 'name'],
                'created_by_user' => ['id', 'name'],
                'updated_by_user' => ['id', 'name'],
            ],
        ]);
});
