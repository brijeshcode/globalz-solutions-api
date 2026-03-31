<?php

use Tests\Feature\Suppliers\Purchases\Concerns\HasPurchaseSetup;

uses(HasPurchaseSetup::class);

uses()->group('api', 'suppliers', 'purchases');

beforeEach(function () {
    $this->setUpPurchases();
});

it('shows a purchase with all relationships', function () {
    $purchase = $this->createPurchaseViaApi();

    $this->getJson(route('suppliers.purchases.show', $purchase))
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

it('returns 404 for non-existent purchase', function () {
    $this->getJson(route('suppliers.purchases.show', 999))->assertNotFound();
});
