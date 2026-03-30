<?php

use Tests\Feature\Customers\ReturnOrders\Concerns\HasCustomerReturnOrderSetup;

uses(HasCustomerReturnOrderSetup::class);

beforeEach(function () {
    $this->setUpCustomerReturnOrders();
    $this->actingAs($this->salesman, 'sanctum');
});

it('returns correct return order statistics', function () {
    for ($i = 0; $i < 3; $i++) {
        $this->createPendingReturn();
    }
    for ($i = 0; $i < 2; $i++) {
        $this->createApprovedReturn();
    }

    $trashed = $this->createPendingReturn();
    $trashed->delete();

    $stats = $this->getJson(route('customers.return-orders.stats'))
        ->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => [
                'total_returns',
                'pending_returns',
                'approved_returns',
                'received_returns',
                'trashed_returns',
                'total_amount',
                'total_amount_usd',
                'returns_by_prefix',
                'returns_by_currency',
                'recent_approved',
            ],
        ])
        ->json('data');

    expect($stats['total_returns'])->toBe(5)
        ->and($stats['pending_returns'])->toBe(3)
        ->and($stats['approved_returns'])->toBe(2)
        ->and($stats['trashed_returns'])->toBe(1);
});
