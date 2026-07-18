<?php

use App\Models\Setting;
use App\Models\User;

beforeEach(function () {
    $this->superAdmin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
    $this->admin      = User::factory()->create(['role' => User::ROLE_ADMIN]);
});

it('returns every module key with its default of 0', function () {
    $this->actingAs($this->admin, 'sanctum');

    $this->getJson(route('settings.module-locks.get'))
        ->assertOk()
        ->assertJsonPath('data.sale', 0)
        ->assertJsonPath('data.sale_order', 0)
        ->assertJsonPath('data.purchase', 0)
        ->assertJsonPath('data.customer_payment', 0)
        ->assertJsonPath('data.customer_payment_order', 0)
        ->assertJsonPath('data.customer_return', 0)
        ->assertJsonPath('data.customer_return_order', 0)
        ->assertJsonPath('data.customer_credit_note', 0)
        ->assertJsonPath('data.supplier_credit_note', 0)
        ->assertJsonPath('data.supplier_payment', 0)
        ->assertJsonPath('data.expense', 0)
        ->assertJsonPath('data.expense_payment', 0);
});

it('lets a super admin update lock days', function () {
    $this->actingAs($this->superAdmin, 'sanctum');

    $this->putJson(route('settings.module-locks.update'), [
        'sale'     => 7,
        'purchase' => 14,
    ])->assertOk();

    expect((int) Setting::get('module_locks', 'sale', 0))->toBe(7)
        ->and((int) Setting::get('module_locks', 'purchase', 0))->toBe(14);
});

it('forbids a non super admin from updating lock days', function () {
    $this->actingAs($this->admin, 'sanctum');

    $this->putJson(route('settings.module-locks.update'), ['sale' => 7])
        ->assertForbidden();
});

it('rejects negative and non-integer values', function () {
    $this->actingAs($this->superAdmin, 'sanctum');

    $this->putJson(route('settings.module-locks.update'), ['sale' => -1])
        ->assertUnprocessable();

    $this->putJson(route('settings.module-locks.update'), ['sale' => 'abc'])
        ->assertUnprocessable();
});

it('resets all lock days to 0', function () {
    $this->actingAs($this->superAdmin, 'sanctum');
    Setting::set('module_locks', 'sale', 7, Setting::TYPE_NUMBER);

    $this->postJson(route('settings.module-locks.reset'))->assertOk();

    expect((int) Setting::get('module_locks', 'sale', 99))->toBe(0);
});
