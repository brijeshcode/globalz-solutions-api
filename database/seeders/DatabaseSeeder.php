<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Create admin users first
        User::factory()->create([
            'name' => 'Admin Master',
            'email' => 'admin@example.com',
            'role' => 'super_admin',
            'password' => '123456'
        ]);

        User::factory()->create([
            'name' => 'Admin 2',
            'email' => 'admin2@example.com',
            'role' => 'admin',
            'password' => '123456'
        ]);

        User::factory()->create([
            'name' => 'Brijesh',
            'email' => 'brijesh@example.com',
            'role' => 'developer',
            'password' => '123456'
        ]);

        User::factory()->create([
            'name' => 'imsaleman',
            'email' => 'imsaleman@example.com',
            'role' => 'salesman',
            'password' => '123456'
        ]);

        User::factory()->create([
            'name' => 'imsaleman2',
            'email' => 'imsaleman2@example.com',
            'role' => 'salesman',
            'password' => '123456'
        ]);

        User::factory()->create([
            'name' => 'Warehouse manager',
            'email' => 'imwarehousemanager@example.com',
            'role' => 'warehouse_manager',
            'password' => '123456'
        ]);

        User::factory()->create([
            'name' => 'Warehouse manager2',
            'email' => 'imwarehousemanager2@example.com',
            'role' => 'warehouse_manager',
            'password' => '123456'
        ]);

        // Seed all setup module data
        $this->call(SetupSeeder::class);
        $this->call(SettingsSeeder::class);
        
        // // Seed development data
        $this->call(SupplierSeeder::class);
        $this->call(EmployeeSeeder::class);
        $this->call(CustomerSeeder::class);
        $this->call(ItemSeeder::class);
    }
}
