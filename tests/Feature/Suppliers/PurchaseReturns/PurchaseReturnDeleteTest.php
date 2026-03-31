<?php

use App\Models\Suppliers\PurchaseReturn;
use Tests\Feature\Suppliers\PurchaseReturns\Concerns\HasPurchaseReturnSetup;

uses(HasPurchaseReturnSetup::class);

uses()->group('api', 'suppliers', 'purchase-returns');

beforeEach(function () {
    $this->setUpPurchaseReturns();
});

it('soft deletes a purchase return', function () {
    $purchaseReturn = PurchaseReturn::factory()->create([
        'supplier_id'  => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'currency_id'  => $this->currency->id,
    ]);

    $this->deleteJson(route('suppliers.purchase-returns.destroy', $purchaseReturn))->assertStatus(204);

    $this->assertSoftDeleted('purchase_returns', ['id' => $purchaseReturn->id]);
});

it('lists trashed purchase returns', function () {
    $purchaseReturn = PurchaseReturn::factory()->create([
        'supplier_id'  => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'currency_id'  => $this->currency->id,
    ]);
    $purchaseReturn->delete();

    $this->getJson(route('suppliers.purchase-returns.trashed'))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('restores a trashed purchase return', function () {
    $purchaseReturn = PurchaseReturn::factory()->create([
        'supplier_id'  => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'currency_id'  => $this->currency->id,
    ]);
    $purchaseReturn->delete();

    $this->patchJson(route('suppliers.purchase-returns.restore', $purchaseReturn->id))->assertOk();

    $this->assertDatabaseHas('purchase_returns', ['id' => $purchaseReturn->id, 'deleted_at' => null]);
});

it('force deletes a purchase return', function () {
    $purchaseReturn = PurchaseReturn::factory()->create([
        'supplier_id'  => $this->supplier->id,
        'warehouse_id' => $this->warehouse->id,
        'currency_id'  => $this->currency->id,
    ]);
    $purchaseReturn->delete();

    $this->deleteJson(route('suppliers.purchase-returns.force-delete', $purchaseReturn->id))->assertStatus(204);

    $this->assertDatabaseMissing('purchase_returns', ['id' => $purchaseReturn->id]);
});
