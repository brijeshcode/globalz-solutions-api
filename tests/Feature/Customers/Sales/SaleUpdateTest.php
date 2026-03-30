<?php

use Tests\Feature\Customers\Sales\Concerns\HasSaleSetup;

uses(HasSaleSetup::class);

beforeEach(function () {
    $this->setUpSales();
});

it('cannot update an approved sale', function () {
    $sale = $this->createSaleViaApi([
        'client_po_number' => 'PO-INITIAL',
        'items'            => [
            ['item_id' => $this->item1->id, 'quantity' => 2, 'price' => 100.00, 'total_price' => 200.00],
        ],
    ]);

    expect($sale->isApproved())->toBe(true);

    $this->putJson(route('customers.sales.update', $sale), [
        'client_po_number' => 'PO-UPDATED',
        'currency_rate'    => 1.15,
        'total'            => 650.00,
        'total_usd'        => 565.22,
        'items'            => [
            ['item_id' => $this->item1->id, 'price' => 105.00, 'quantity' => 4, 'total_price' => 420.00],
        ],
    ])->assertUnprocessable()
      ->assertJson(['message' => 'Cannot update an approved sales. Use sale orders endpoint instead.']);
});
