<?php

use Tests\Feature\Customers\ReturnOrders\Concerns\HasCustomerReturnOrderSetup;

uses(HasCustomerReturnOrderSetup::class);

beforeEach(function () {
    $this->setUpCustomerReturnOrders();
    $this->actingAs($this->salesman, 'sanctum');
});

// --- Soft Delete ---

it('can soft delete a pending return order', function () {
    $return = $this->createPendingReturn();

    $this->deleteJson(route('customers.return-orders.destroy', $return))
        ->assertNoContent();

    $this->assertSoftDeleted('customer_returns', ['id' => $return->id]);
});

it('cannot soft delete an approved return order', function () {
    $return = $this->createApprovedReturn();

    $this->deleteJson(route('customers.return-orders.destroy', $return))
        ->assertUnprocessable()
        ->assertJson(['message' => 'Cannot delete approved returns']);
});

// --- Trashed ---

it('lists trashed return orders', function () {
    $return = $this->createPendingReturn();
    $return->delete();

    $this->getJson(route('customers.return-orders.trashed'))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

// --- Restore ---

it('admin can restore a trashed return order', function () {
    $this->actingAs($this->admin, 'sanctum');
    $return = $this->createPendingReturn();
    $return->delete();

    $this->patchJson(route('customers.return-orders.restore', $return->id))
        ->assertOk();

    $this->assertDatabaseHas('customer_returns', ['id' => $return->id, 'deleted_at' => null]);
});

// --- Force Delete ---

it('admin can permanently delete a trashed return order', function () {
    $this->actingAs($this->admin, 'sanctum');
    $return = $this->createPendingReturn();
    $return->delete();

    $this->deleteJson(route('customers.return-orders.force-delete', $return->id))
        ->assertNoContent();

    $this->assertDatabaseMissing('customer_returns', ['id' => $return->id]);
});
