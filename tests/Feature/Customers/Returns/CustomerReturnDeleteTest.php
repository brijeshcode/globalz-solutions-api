<?php

use Tests\Feature\Customers\Returns\Concerns\HasCustomerReturnSetup;

uses(HasCustomerReturnSetup::class);

beforeEach(function () {
    $this->setUpCustomerReturns();
    $this->actingAs($this->admin, 'sanctum');
});

// --- Trashed ---

it('lists trashed returns', function () {
    $return = $this->createApprovedReturn();
    $return->delete();

    $this->getJson(route('customers.returns.trashed'))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

// --- Restore ---

it('admin can restore a trashed return', function () {
    $return = $this->createApprovedReturn();
    $return->delete();

    $this->patchJson(route('customers.returns.restore', $return->id))
        ->assertOk();

    $this->assertDatabaseHas('customer_returns', ['id' => $return->id, 'deleted_at' => null]);
});

it('employee cannot restore returns', function () {
    $this->actingAs($this->salesman, 'sanctum');
    $return = $this->createApprovedReturn();
    $return->delete();

    $this->patchJson(route('customers.returns.restore', $return->id))
        ->assertForbidden()
        ->assertJson(['message' => 'Only admins can restore returns']);
});

it('can only restore approved returns', function () {
    $return = $this->createPendingReturn();
    $return->delete();

    $this->patchJson(route('customers.returns.restore', $return->id))
        ->assertUnprocessable()
        ->assertJson(['message' => 'Can only restore approved returns']);
});

// --- Force Delete ---

it('admin can permanently delete a trashed return', function () {
    $return = $this->createApprovedReturn();
    $return->delete();

    $this->deleteJson(route('customers.returns.force-delete', $return->id))
        ->assertNoContent();

    $this->assertDatabaseMissing('customer_returns', ['id' => $return->id]);
});

it('employee cannot permanently delete returns', function () {
    $this->actingAs($this->salesman, 'sanctum');
    $return = $this->createApprovedReturn();
    $return->delete();

    $this->deleteJson(route('customers.returns.force-delete', $return->id))
        ->assertForbidden()
        ->assertJson(['message' => 'Only admins can permanently delete returns']);
});
