<?php

use App\Models\Customers\CustomerReturn;
use App\Models\Setting;
use Tests\Feature\Customers\ReturnOrders\Concerns\HasCustomerReturnOrderSetup;

uses(HasCustomerReturnOrderSetup::class);

beforeEach(function () {
    $this->setUpCustomerReturnOrders();
    $this->actingAs($this->salesman, 'sanctum');
});

it('auto-generates a 6-digit code on creation', function () {
    $this->postJson(route('customers.return-orders.store'), $this->returnPayload())
        ->assertCreated();

    $return = CustomerReturn::latest()->first();
    expect($return->code)->not()->toBeNull()
        ->and($return->code)->toMatch('/^\d{6}$/')
        ->and($return->return_code)->toBe($return->prefix . $return->code);
});

it('increments the counter sequentially', function () {
    CustomerReturn::withTrashed()->forceDelete();
    Setting::where('group_name', 'customer_returns')
        ->where('key_name', 'code_counter')
        ->update(['value' => '999']);

    $payload1         = $this->returnPayload();
    $payload1['items'][0]['item_code'] = 'ITEM001-1';
    $return1 = CustomerReturn::create(array_merge($payload1, [
        'created_by' => $this->salesman->id,
        'updated_by' => $this->salesman->id,
    ]));

    $payload2         = $this->returnPayload();
    $payload2['items'][0]['item_code'] = 'ITEM001-2';
    $return2 = CustomerReturn::create(array_merge($payload2, [
        'created_by' => $this->salesman->id,
        'updated_by' => $this->salesman->id,
    ]));

    expect((int) $return1->code)->toBeGreaterThanOrEqual(1000)
        ->and((int) $return2->code)->toBe((int) $return1->code + 1);
});
