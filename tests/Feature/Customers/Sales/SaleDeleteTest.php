<?php

use App\Services\Inventory\InventoryService;
use Tests\Feature\Customers\Sales\Concerns\HasSaleSetup;

uses(HasSaleSetup::class);

beforeEach(function () {
    $this->setUpSales();
});

it('cannot soft delete an approved sale', function () {
    $sale = $this->createApprovedSale();

    $this->deleteJson(route('customers.sales.destroy', $sale))
        ->assertUnprocessable()
        ->assertJson(['message' => 'Cannot delete an approved sales. Use sale orders endpoint instead.']);
});

it('lists trashed sales', function () {
    $sale = $this->createApprovedSale();
    $sale->delete();

    $this->getJson(route('customers.sales.trashed'))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('can restore a trashed sale', function () {
    $sale = $this->createApprovedSale();
    $sale->delete();

    $this->patchJson(route('customers.sales.restore', $sale->id))->assertOk();

    $this->assertDatabaseHas('sales', ['id' => $sale->id, 'deleted_at' => null]);
});

it('can permanently delete a trashed sale', function () {
    $sale = $this->createApprovedSale();
    $sale->delete();

    $this->deleteJson(route('customers.sales.force-delete', $sale->id))
        ->assertStatus(204);

    $this->assertDatabaseMissing('sales', ['id' => $sale->id]);
});

it('restores inventory when a sale is soft deleted', function () {
    $before = InventoryService::getQuantity($this->item1->id, $this->warehouse->id);

    $sale = $this->createSaleViaApi([
        'items' => [['item_id' => $this->item1->id, 'quantity' => 5, 'price' => 100.00, 'total_price' => 500.00]],
    ]);

    expect(InventoryService::getQuantity($this->item1->id, $this->warehouse->id))->toBe($before - 5);

    $sale->delete();

    expect(InventoryService::getQuantity($this->item1->id, $this->warehouse->id))->toBe($before);
});
