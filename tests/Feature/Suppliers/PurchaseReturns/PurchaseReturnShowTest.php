<?php

use Tests\Feature\Suppliers\PurchaseReturns\Concerns\HasPurchaseReturnSetup;

uses(HasPurchaseReturnSetup::class);

uses()->group('api', 'suppliers', 'purchase-returns');

beforeEach(function () {
    $this->setUpPurchaseReturns();
});

it('shows purchase return with all relationships', function () {
    $this->setupInitialInventory($this->item1->id, 50, 30.00);

    $purchaseReturn = $this->createPurchaseReturnViaApi();

    $this->getJson(route('suppliers.purchase-returns.show', $purchaseReturn))
        ->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => [
                'id',
                'code',
                'supplier'  => ['id', 'name', 'code'],
                'warehouse' => ['id', 'name'],
                'currency'  => ['id', 'name', 'code', 'symbol'],
                'items'     => [
                    '*' => ['id', 'item' => ['id', 'code', 'name'], 'price', 'quantity', 'total_price'],
                ],
            ],
        ]);
});
