<?php

namespace Database\Seeders\Landlord;

use App\Models\Tenant;
use Illuminate\Database\Seeder;

class TenantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Tenant::create([
        //     'name' => 'Staging',
        //     'tenant_key' => 'staging',
        //     'domain' => 'staging.globalzsolutions.local',
        //     'database' => 'nick_globalz_tenants_staging',
        //     'database_username' => 'root',
        //     'database_password' => '', // Will be encrypted
        //     'is_active' => true,
        // ]);

        // Tenant::create([
        //     'name' => 'Live',
        //     'tenant_key' => 'live',
        //     'domain' => 'live.globalzsolutions.local',
        //     'database' => 'nick_globalz_tenants_live',
        //     'database_username' => 'root',
        //     'database_password' => '',
        //     'is_active' => true,
        // ]);

        // Tenant::create([
        //     'name' => 'david',
            // 'tenant_key' => 'david',
        //     'domain' => 'david.globalzsolutions.local',
        //     'database' => 'nick_globalz_tenants_live',
        //     'database_username' => 'root',
        //     'database_password' => '',
        //     'is_active' => true,
        // ]);

        Tenant::create([
            'name' => 'Live',
            'tenant_key' => 'live',
            'domain' => 'localhost',
            'database' => 'nick_globalz_tenants_live',
            'database_username' => 'root',
            'database_password' => '',
            'is_active' => true,
        ]);

        Tenant::create([
            'name' => 'ivy supply',
            'tenant_key' => 'ivysupply',
            'domain' => 'localhost-1',
            'database' => 'nick_globalz_tenants_ivy',
            'database_username' => 'root',
            'database_password' => '',
            'is_active' => true,
        ]);
    }
}
