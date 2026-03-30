<?php

use Tests\Feature\Customers\ReturnOrders\Concerns\HasCustomerReturnOrderSetup;

uses(HasCustomerReturnOrderSetup::class);

// Approval actions are admin-only, so default to admin for this file
beforeEach(function () {
    $this->setUpCustomerReturnOrders();
    $this->actingAs($this->admin, 'sanctum');
});

it('admin approves a pending return order', function () {
    $return = $this->createPendingReturn();

    $this->patchJson(route('customers.return-orders.approve', $return), [
        'approve_note' => 'Approved by admin',
    ])->assertOk()
      ->assertJson(['data' => ['status' => 'approved', 'is_approved' => true, 'is_pending' => false]]);

    $return->refresh();
    expect($return->isApproved())->toBeTrue()
        ->and($return->approved_by)->toBe($this->admin->id)
        ->and($return->approve_note)->toBe('Approved by admin');
});

it('salesman cannot approve return orders', function () {
    $this->actingAs($this->salesman, 'sanctum');
    $return = $this->createPendingReturn();

    $this->patchJson(route('customers.return-orders.approve', $return), [
        'approve_note' => 'Trying to approve as salesman',
    ])->assertForbidden()
      ->assertJson(['message' => 'You do not have permission to approve returns']);
});
