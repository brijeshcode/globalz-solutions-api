<?php

use App\Models\Customers\Customer;
use App\Models\Setting;
use Tests\Feature\Customers\Customers\Concerns\HasCustomerSetup;

uses(HasCustomerSetup::class);

beforeEach(function () {
    $this->setUpCustomers();
});

it('auto-generates customer code on creation', function () {
    $this->postJson(route('customers.store'), $this->customerPayload(['name' => 'Auto Code Customer']))
        ->assertCreated();

    $customer = Customer::where('name', 'Auto Code Customer')->first();
    expect($customer->code)->not()->toBeNull()
        ->and((int) $customer->code)->toBeGreaterThanOrEqual(41101364);
});

it('generates sequential codes', function () {
    Customer::withTrashed()->forceDelete();

    $code1 = (int) $this->postJson(route('customers.store'), $this->customerPayload(['name' => 'First Customer']))
        ->assertCreated()->json('data.code');

    $code2 = (int) $this->postJson(route('customers.store'), $this->customerPayload(['name' => 'Second Customer']))
        ->assertCreated()->json('data.code');

    expect($code1)->toBeGreaterThanOrEqual(41101364)
        ->and($code2)->toBe($code1 + 1);
});

it('returns next available code', function () {
    $response = $this->getJson(route('customers.next-code'))
        ->assertOk()
        ->assertJsonStructure(['message', 'data' => ['code', 'is_available', 'message']]);

    expect((int) $response->json('data.code'))->toBeGreaterThanOrEqual(41101364)
        ->and($response->json('data.is_available'))->toBe(true);
});

it('returns the correct code for a known counter value', function () {
    Setting::set('customers', 'code_counter', 50000001, 'number');

    $response = $this->getJson(route('customers.next-code'))->assertOk();

    expect((int) $response->json('data.code'))->toBe(50000001)
        ->and($response->json('data.is_available'))->toBe(true);
});

it('increments counter after each creation', function () {
    Setting::set('customers', 'code_counter', 50000005, 'number');

    $code = (int) $this->postJson(route('customers.store'), $this->customerPayload(['name' => 'Counter Test Customer']))
        ->assertCreated()->json('data.code');

    expect($code)->toBe(50000005);

    $nextCode = (int) $this->getJson(route('customers.next-code'))->json('data.code');
    expect($nextCode)->toBe(50000006);
});

it('auto-creates missing code counter setting with default value', function () {
    Setting::where('group_name', 'customers')->where('key_name', 'code_counter')->delete();
    Setting::clearCache();

    $response = $this->getJson(route('customers.next-code'))->assertOk();
    expect((int) $response->json('data.code'))->toBe(41101364);

    $setting = Setting::where('group_name', 'customers')->where('key_name', 'code_counter')->first();
    expect($setting)->not()->toBeNull()
        ->and($setting->data_type)->toBe('number')
        ->and($setting->value)->toBe('41101364');
});

it('handles full counter progression correctly', function () {
    Setting::set('customers', 'code_counter', 41101364, 'number');

    $code1 = (int) $this->postJson(route('customers.store'), $this->customerPayload(['name' => 'First Customer']))
        ->assertCreated()->json('data.code');
    expect($code1)->toBe(41101364);

    $code2 = (int) $this->postJson(route('customers.store'), $this->customerPayload(['name' => 'Second Customer']))
        ->assertCreated()->json('data.code');
    expect($code2)->toBe(41101365);

    $nextCode = (int) $this->getJson(route('customers.next-code'))->json('data.code');
    expect($nextCode)->toBe(41101366);
});

it('auto-generates counter setting and continues sequence when missing', function () {
    Customer::withTrashed()->forceDelete();
    Setting::where('group_name', 'customers')->where('key_name', 'code_counter')->delete();
    Setting::clearCache();

    expect(Setting::where('group_name', 'customers')->where('key_name', 'code_counter')->first())->toBeNull();

    $code1 = (int) $this->postJson(route('customers.store'), $this->customerPayload(['name' => 'First Customer']))
        ->assertCreated()->json('data.code');
    expect($code1)->toBe(41101364);

    $setting = Setting::where('group_name', 'customers')->where('key_name', 'code_counter')->first();
    expect($setting)->not()->toBeNull()
        ->and($setting->data_type)->toBe('number')
        ->and((int) $setting->value)->toBe(41101365);

    $code2 = (int) $this->postJson(route('customers.store'), $this->customerPayload(['name' => 'Second Customer']))
        ->assertCreated()->json('data.code');
    expect($code2)->toBe(41101365);

    $code3 = (int) $this->postJson(route('customers.store'), $this->customerPayload(['name' => 'Third Customer']))
        ->assertCreated()->json('data.code');
    expect($code3)->toBe(41101366);

    $finalSetting = Setting::where('group_name', 'customers')->where('key_name', 'code_counter')->first();
    expect((int) $finalSetting->value)->toBe(41101367);

    $nextCode = (int) $this->getJson(route('customers.next-code'))->json('data.code');
    expect($nextCode)->toBe(41101367);
});
