<?php

use App\Models\Suppliers\Purchase;
use Tests\Feature\Suppliers\Purchases\Concerns\HasPurchaseSetup;

uses(HasPurchaseSetup::class);

uses()->group('api', 'suppliers', 'purchases');

beforeEach(function () {
    $this->setUpPurchases();
});

it('soft deletes a purchase', function () {
    $purchase = Purchase::factory()->create(['supplier_id' => $this->supplier->id]);

    $this->deleteJson(route('suppliers.purchases.destroy', $purchase))->assertStatus(204);

    $this->assertSoftDeleted('purchases', ['id' => $purchase->id]);
});

it('lists trashed purchases', function () {
    $purchase = Purchase::factory()->create(['supplier_id' => $this->supplier->id]);
    $purchase->delete();

    $this->getJson(route('suppliers.purchases.trashed'))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
