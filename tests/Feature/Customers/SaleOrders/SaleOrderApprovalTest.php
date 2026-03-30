<?php

use Tests\Feature\Customers\SaleOrders\Concerns\HasSaleOrderSetup;

uses(HasSaleOrderSetup::class);

// Approval actions are admin-only, so default to admin for this file
beforeEach(function () {
    $this->setUpSaleOrders();
    $this->actingAs($this->admin, 'sanctum');
});

it('admin approves a pending sale order', function () {
    $sale = $this->createPendingSaleOrder();

    $this->patchJson(route('customers.sale-orders.approve', $sale), [
        'approve_note' => 'Approved by admin',
    ])->assertOk()
      ->assertJson(['data' => ['is_approved' => true, 'is_pending' => false]]);

    $sale->refresh();
    expect($sale->isApproved())->toBeTrue()
        ->and($sale->approved_by)->toBe($this->admin->id)
        ->and($sale->approve_note)->toBe('Approved by admin');
});

it('salesman cannot approve sale orders', function () {
    $this->actingAs($this->salesman, 'sanctum');
    $sale = $this->createPendingSaleOrder();

    $this->patchJson(route('customers.sale-orders.approve', $sale), [
        'approve_note' => 'Trying to approve as salesman',
    ])->assertForbidden()
      ->assertJson(['message' => 'You do not have permission to approve sales']);
});
