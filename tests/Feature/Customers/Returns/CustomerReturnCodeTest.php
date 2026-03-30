<?php

use App\Models\Customers\CustomerReturn;
use App\Models\Setting;
use Tests\Feature\Customers\Returns\Concerns\HasCustomerReturnSetup;

uses(HasCustomerReturnSetup::class);

beforeEach(function () {
    $this->setUpCustomerReturns();
    $this->actingAs($this->admin, 'sanctum');
});

it('auto-generates a 6-digit code on creation', function () {
    $this->postJson(route('customers.returns.store'), $this->returnPayload())
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

    $base = $this->returnPayload();
    unset($base['items']); // items not needed for direct model creation

    $return1 = CustomerReturn::create(array_merge($base, [
        'approved_by' => $this->admin->id,
        'approved_at' => now(),
        'created_by'  => $this->admin->id,
        'updated_by'  => $this->admin->id,
    ]));

    $return2 = CustomerReturn::create(array_merge($base, [
        'approved_by' => $this->admin->id,
        'approved_at' => now(),
        'created_by'  => $this->admin->id,
        'updated_by'  => $this->admin->id,
    ]));

    expect((int) $return1->code)->toBeGreaterThanOrEqual(1000)
        ->and((int) $return2->code)->toBe((int) $return1->code + 1);
});
