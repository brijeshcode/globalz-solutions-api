<?php

use Tests\Feature\Customers\SaleOrders\Concerns\HasSaleOrderSetup;

uses(HasSaleOrderSetup::class);

beforeEach(function () {
    $this->setUpSaleOrders();
    $this->actingAs($this->salesman, 'sanctum');
});

// --- Soft Delete ---

it('can soft delete a pending sale order', function () {
    $sale = $this->createPendingSaleOrder();

    $this->deleteJson(route('customers.sale-orders.destroy', $sale))
        ->assertNoContent();

    $this->assertSoftDeleted('sales', ['id' => $sale->id]);
});

it('cannot soft delete an approved sale order', function () {
    $sale = $this->createApprovedSaleOrder();

    $this->deleteJson(route('customers.sale-orders.destroy', $sale))
        ->assertUnprocessable()
        ->assertJson(['message' => 'Cannot delete approved sales']);
});

// --- Trashed ---

it('lists trashed sale orders', function () {
    $sale = $this->createPendingSaleOrder();
    $sale->delete();

    $this->getJson(route('customers.sale-orders.trashed'))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

// --- Restore ---

it('salesman can restore their own trashed sale order', function () {
    $sale = $this->createPendingSaleOrder();
    $sale->delete();

    $this->patchJson(route('customers.sale-orders.restore', $sale->id))
        ->assertOk();

    $this->assertDatabaseHas('sales', ['id' => $sale->id, 'deleted_at' => null]);
});

// --- Force Delete ---

it('admin can permanently delete a trashed sale order', function () {
    $this->actingAs($this->admin, 'sanctum');
    $sale = $this->createPendingSaleOrder();
    $sale->delete();

    $this->deleteJson(route('customers.sale-orders.force-delete', $sale->id))
        ->assertNoContent();

    $this->assertDatabaseMissing('sales', ['id' => $sale->id]);
});

it('salesman cannot permanently delete sale orders', function () {
    $sale = $this->createPendingSaleOrder();
    $sale->delete();

    $this->deleteJson(route('customers.sale-orders.force-delete', $sale->id))
        ->assertForbidden()
        ->assertJson(['message' => 'Only admins can permanently delete sales']);
});
