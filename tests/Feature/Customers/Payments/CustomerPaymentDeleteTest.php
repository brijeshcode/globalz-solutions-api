<?php

use Tests\Feature\Customers\Payments\Concerns\HasCustomerPaymentSetup;

uses(HasCustomerPaymentSetup::class);

beforeEach(function () {
    $this->setUpCustomerPayments();
    $this->actingAs($this->admin, 'sanctum');
});

it('cannot soft delete an approved payment', function () {
    $payment = $this->createPaymentViaFactory('approved');

    $this->deleteJson(route('customers.payments.destroy', $payment))
        ->assertUnprocessable()
        ->assertJson(['message' => 'Cannot delete approved payments']);
});

it('lists trashed payments', function () {
    $payment = $this->createPaymentViaFactory('approved');
    $payment->delete();

    $this->getJson(route('customers.payments.trashed'))
        ->assertOk()
        ->assertJsonCount(1, 'data');
});

it('restores a trashed payment', function () {
    $payment = $this->createPaymentViaFactory('approved');
    $payment->delete();

    $this->patchJson(route('customers.payments.restore', $payment->id))
        ->assertOk();

    $this->assertDatabaseHas('customer_payments', ['id' => $payment->id, 'deleted_at' => null]);
});

it('permanently deletes a trashed payment', function () {
    $payment = $this->createPaymentViaFactory('approved');
    $payment->delete();

    $this->deleteJson(route('customers.payments.force-delete', $payment->id))
        ->assertStatus(204);

    $this->assertDatabaseMissing('customer_payments', ['id' => $payment->id]);
});
