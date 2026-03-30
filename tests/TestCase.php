<?php

namespace Tests;

use App\Models\Tenant;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Tests\Support\TestFeatureSeeder;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;

    /** Shared across all tests — created once per suite. */
    protected static ?Tenant $sharedTenant = null;

    public function refreshDatabase()
    {
        if (! RefreshDatabaseState::$migrated) {

            // Landlord DB — only the tenants table (nick_globalzTest)
            $this->artisan('migrate:fresh', [
                '--database' => 'mysql',
                '--path'     => 'database/migrations/landlord',
            ]);

            // Tenant DB — all app tables (nick_globalzTest_tenant)
            $this->artisan('migrate:fresh', [
                '--database' => 'tenant',
                '--path'     => 'database/migrations',
            ]);

            $this->app[Kernel::class]->setArtisan(null);
            RefreshDatabaseState::$migrated = true;

            // Create tenant record + configure DB connection — once per suite.
            // Must be BEFORE beginDatabaseTransaction() so the DB::purge inside
            // makeCurrent() does not destroy the open transaction.
            $this->initTenant();
        }

        // App container is recreated per test — restore the two things makeCurrent() set:
        // 1. default connection → tenant DB
        // 2. tenant binding in container
        // (No DB::purge needed — connections reconnect automatically from env config)
        config(['database.default' => 'tenant']);
        $this->tenant = static::$sharedTenant;
        app()->instance(config('multitenancy.current_tenant_container_key'), $this->tenant);
        $this->withHeaders(['X-Company-Domain' => 'test.example.com']);

        // Wrap each test in a transaction on the tenant DB, rolled back on teardown.
        $this->beginDatabaseTransaction();
    }

    /**
     * Called once per suite. Creates the tenant record and calls makeCurrent()
     * to bind the tenant and configure the default DB connection.
     */
    protected function initTenant(): void
    {
        static::$sharedTenant = Tenant::on('mysql')->create([
            'name'              => 'Test Tenant',
            'tenant_key'        => 'test',
            'domain'            => 'test.example.com',
            'database'          => config('database.connections.tenant.database'),
            'database_username' => config('database.connections.tenant.username'),
            'database_password' => null,
            'is_active'         => true,
        ]);

        static::$sharedTenant->makeCurrent();

        TestFeatureSeeder::seed(static::$sharedTenant);
    }
}
