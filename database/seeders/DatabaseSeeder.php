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
        // User::factory(10)->create();

        User::factory()->create([
            'name' => 'Admin Master',
            'email' => 'admin@example.com',
            'role' => 'super_admin',
            'password' => '123456'
        ]);

        User::factory()->create([
            'name' => 'Brijesh',
            'email' => 'brijesh@example.com',
            'role' => 'developer',
            'password' => '123456'
        ]);
    }
}
