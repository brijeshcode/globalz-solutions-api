<?php

use Tests\Feature\Customers\PaymentOrders\Concerns\HasCustomerPaymentOrderSetup;

uses(HasCustomerPaymentOrderSetup::class);

beforeEach(function () {
    $this->setUpCustomerPaymentOrders();
    $this->actingAs($this->salesman, 'sanctum');
});

// --- Soft Delete ---

it('can soft delete a pending payment order', function () {
    $payment = $this->createOrderViaApi();

    $this->deleteJson(route('customers.payment-orders.destroy', $payment))
        ->assertStatus(204);

    $this->assertSoftDeleted('customer_payments', ['id' => $payment->id]);
});

it('cannot soft delete an approved payment order', function () {
    $this->actingAs($this->admin, 'sanctum');
    $payment = $this->createOrderViaFactory('approved');

    $this->deleteJson(route('customers.payment-orders.destroy', $payment))
        ->assertUnprocessable()
        ->assertJson(['message' => 'Cannot delete approved payments']);
});

// --- Trashed ---

it('lists trashed payment orders', function () {
    $payment = $this->createOrderViaFactory('pending');
    $payment->delete();

    $this->getJson(route('customers.payment-orders.trashed'))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

// --- Restore ---

it('restores a trashed payment order', function () {
    $payment = $this->createOrderViaFactory('pending');
    $payment->delete();

    $this->patchJson(route('customers.payment-orders.restore', $payment->id))
        ->assertOk();

    $this->assertDatabaseHas('customer_payments', ['id' => $payment->id, 'deleted_at' => null]);
});

// --- Force Delete ---

it('permanently deletes a trashed payment order', function () {
    $payment = $this->createOrderViaFactory('pending');
    $payment->delete();

    $this->deleteJson(route('customers.payment-orders.force-delete', $payment->id))
        ->assertStatus(204);

    $this->assertDatabaseMissing('customer_payments', ['id' => $payment->id]);
});
