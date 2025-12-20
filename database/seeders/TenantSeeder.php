<?php

namespace Database\Seeders;

use App\Models\Tenant;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class TenantSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Tenant::create([
            'name' => 'Staging',
            'domain' => 'staging.globalzsolutions.local',
            'database' => 'nick_globalz_tenants_staging',
            'database_username' => 'root',
            'database_password' => '', // Will be encrypted
            'is_active' => true,
        ]);

        Tenant::create([
            'name' => 'Live',
            'domain' => 'live.globalzsolutions.local',
            'database' => 'nick_globalz_tenants_live',
            'database_username' => 'root',
            'database_password' => '',
            'is_active' => true,
        ]);

        // Tenant::create([
        //     'name' => 'david',
        //     'domain' => 'david.globalzsolutions.local',
        //     'database' => 'nick_globalz_tenants_live',
        //     'database_username' => 'root',
        //     'database_password' => '',
        //     'is_active' => true,
        // ]);
    }
}
