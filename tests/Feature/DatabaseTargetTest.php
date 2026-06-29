<?php

use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

it('targets the landlord test database', function () {
    $database = DB::connection('mysql')->getDatabaseName();

    expect($database)->toBe('nick_globalzTest');
});

it('targets the tenant test database', function () {
    $database = DB::connection('tenant')->getDatabaseName();

    expect($database)->toBe('nick_globalzTest_tenant');
});

it('tenant record exists in landlord test database with correct domain', function () {
    $tenant = Tenant::on('mysql')->where('domain', 'test.example.com')->first();

    expect($tenant)->not->toBeNull()
        ->and($tenant->domain)->toBe('test.example.com')
        ->and($tenant->database)->toBe('nick_globalzTest_tenant');
});

it('current tenant is bound and points to tenant test database', function () {
    $tenant = app('currentTenant');

    expect($tenant)->not->toBeNull()
        ->and($tenant->domain)->toBe('test.example.com')
        ->and($tenant->database)->toBe('nick_globalzTest_tenant');
});
