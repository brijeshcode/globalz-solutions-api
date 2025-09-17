<?php

namespace Database\Seeders;

use App\Models\Employees\Employee;
use App\Models\Setups\Employees\Department;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class EmployeeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Creating employees for each department...');

        // Get all departments
        $departments = Department::where('is_active', true)->get();
        $users = User::all();

        if ($departments->isEmpty()) {
            $this->command->error('No active departments found. Please run DepartmentSeeder first.');
            return;
        }

        // Employee data templates for each department
        $employeeTemplates = [
            'Sales' => [
                [
                    'name' => 'Sarah Johnson',
                    'address' => '123 Sales Street, Business District',
                    'phone' => '+1-555-0101',
                    'mobile' => '+1-555-0102',
                    'email' => 'sarah.johnson@company.com',
                    'start_date' => '2023-01-15',
                    'notes' => 'Senior sales representative with 5 years experience'
                ],
                [
                    'name' => 'Mark Thompson',
                    'address' => '456 Commerce Ave, Downtown',
                    'phone' => '+1-555-0103',
                    'mobile' => '+1-555-0104',
                    'email' => 'mark.thompson@company.com',
                    'start_date' => '2023-03-20',
                    'notes' => 'Junior sales representative, specializes in new accounts'
                ]
            ],
            'Accounting' => [
                [
                    'name' => 'Emily Rodriguez',
                    'address' => '789 Finance Boulevard, Corporate Center',
                    'phone' => '+1-555-0201',
                    'mobile' => '+1-555-0202',
                    'email' => 'emily.rodriguez@company.com',
                    'start_date' => '2022-08-10',
                    'notes' => 'Senior accountant, handles accounts payable and receivable'
                ],
                [
                    'name' => 'James Wilson',
                    'address' => '321 Accounting Lane, Financial District',
                    'phone' => '+1-555-0203',
                    'mobile' => '+1-555-0204',
                    'email' => 'james.wilson@company.com',
                    'start_date' => '2023-02-28',
                    'notes' => 'Junior accountant, assists with financial reporting'
                ]
            ],
            'Shipping' => [
                [
                    'name' => 'David Martinez',
                    'address' => '654 Logistics Road, Industrial Area',
                    'phone' => '+1-555-0301',
                    'mobile' => '+1-555-0302',
                    'email' => 'david.martinez@company.com',
                    'start_date' => '2022-11-05',
                    'notes' => 'Shipping coordinator, manages outbound deliveries'
                ],
                [
                    'name' => 'Lisa Chen',
                    'address' => '987 Transport Street, Shipping Zone',
                    'phone' => '+1-555-0303',
                    'mobile' => '+1-555-0304',
                    'email' => 'lisa.chen@company.com',
                    'start_date' => '2023-04-12',
                    'notes' => 'Shipping clerk, handles documentation and tracking'
                ]
            ],
            'Administration' => [
                [
                    'name' => 'Michael Brown',
                    'address' => '147 Admin Plaza, Executive Tower',
                    'phone' => '+1-555-0401',
                    'mobile' => '+1-555-0402',
                    'email' => 'michael.brown@company.com',
                    'start_date' => '2021-12-01',
                    'notes' => 'Administrative manager, oversees office operations'
                ],
                [
                    'name' => 'Jennifer Davis',
                    'address' => '258 Management Way, Corporate Building',
                    'phone' => '+1-555-0403',
                    'mobile' => '+1-555-0404',
                    'email' => 'jennifer.davis@company.com',
                    'start_date' => '2023-01-08',
                    'notes' => 'Administrative assistant, handles HR and office support'
                ]
            ],
            'Warehouse' => [
                [
                    'name' => 'Robert Garcia',
                    'address' => '369 Warehouse Drive, Storage District',
                    'phone' => '+1-555-0501',
                    'mobile' => '+1-555-0502',
                    'email' => 'robert.garcia@company.com',
                    'start_date' => '2022-09-15',
                    'notes' => 'Warehouse supervisor, manages inventory and staff'
                ],
                [
                    'name' => 'Amanda Taylor',
                    'address' => '741 Storage Lane, Industrial Park',
                    'phone' => '+1-555-0503',
                    'mobile' => '+1-555-0504',
                    'email' => 'amanda.taylor@company.com',
                    'start_date' => '2023-05-22',
                    'notes' => 'Warehouse clerk, handles receiving and stock management'
                ]
            ]
        ];

        DB::transaction(function () use ($departments, $employeeTemplates, $users) {
            foreach ($departments as $department) {
                $departmentName = $department->name;

                // Get employee templates for this department, or use generic if not found
                $templates = $employeeTemplates[$departmentName] ?? [
                    [
                        'name' => "Employee 1 - {$departmentName}",
                        'address' => "123 {$departmentName} Street",
                        'phone' => '+1-555-0001',
                        'mobile' => '+1-555-0002',
                        'email' => strtolower("employee1.{$departmentName}@company.com"),
                        'start_date' => '2023-01-01',
                        'notes' => "Employee in {$departmentName} department"
                    ],
                    [
                        'name' => "Employee 2 - {$departmentName}",
                        'address' => "456 {$departmentName} Avenue",
                        'phone' => '+1-555-0003',
                        'mobile' => '+1-555-0004',
                        'email' => strtolower("employee2.{$departmentName}@company.com"),
                        'start_date' => '2023-02-01',
                        'notes' => "Employee in {$departmentName} department"
                    ]
                ];

                foreach ($templates as $template) {
                    $employee = new Employee();
                    $employee->fill($template);
                    $employee->department_id = $department->id;
                    $employee->is_active = true;

                    // Set employee code
                    $employee->code = Employee::getCode();
                    Employee::reserveNextCode();

                    // Set created by user
                    if ($users->count() > 0) {
                        $employee->created_by = $users->random()->id;
                    }

                    $employee->save();

                    $this->command->info("Created employee: {$employee->code} - {$employee->name} ({$departmentName})");
                }
            }
        });

        $this->command->info('Successfully created 2 employees for each department!');
    }
}