<?php

use App\Models\Customers\Sale;
use App\Models\Setting;
use Tests\Feature\Customers\SaleOrders\Concerns\HasSaleOrderSetup;

uses(HasSaleOrderSetup::class);

beforeEach(function () {
    $this->setUpSaleOrders();
    $this->actingAs($this->salesman, 'sanctum');
});

it('auto-generates a 6-digit code on creation', function () {
    $this->postJson(route('customers.sale-orders.store'), $this->saleOrderPayload())->assertCreated();

    $sale = Sale::latest()->first();
    expect($sale->code)->not()->toBeNull()
        ->and($sale->code)->toMatch('/^\d{6}$/')
        ->and($sale->sale_code)->toBe($sale->prefix . $sale->code);
});

it('increments the counter sequentially', function () {
    Sale::withTrashed()->forceDelete();
    Setting::where('group_name', 'sales')->where('key_name', 'code_counter')->update(['value' => '999']);

    $base = $this->saleOrderPayload();

    $sale1 = Sale::create(array_merge($base, ['created_by' => $this->salesman->id, 'updated_by' => $this->salesman->id]));
    $sale2 = Sale::create(array_merge($base, ['created_by' => $this->salesman->id, 'updated_by' => $this->salesman->id]));

    expect((int) $sale1->code)->toBeGreaterThanOrEqual(1000)
        ->and((int) $sale2->code)->toBe((int) $sale1->code + 1);
});
