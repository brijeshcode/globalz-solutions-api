<?php

namespace Database\Seeders;

use App\Models\Setups\Employees\Department;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $departments = config('app.default_departments', [
            'Sales',
            'Accounting',
            'Shipping',
            'Administration'
        ]);

        foreach ($departments as $departmentName) {
            Department::firstOrCreate(
                ['name' => $departmentName],
                [
                    'name' => $departmentName,
                    'description' => "Default {$departmentName} department",
                    'is_active' => true
                ]
            );
        }

        $this->command->info('Default departments created successfully!');
    }
}