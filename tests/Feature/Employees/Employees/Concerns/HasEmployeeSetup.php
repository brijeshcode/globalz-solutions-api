<?php

namespace Tests\Feature\Employees\Employees\Concerns;

use App\Models\Setups\Employees\Department;
use App\Models\User;

trait HasEmployeeSetup
{
    protected User $admin;
    protected Department $department;

    public function setUpEmployees(): void
    {
        $this->admin = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $this->actingAs($this->admin, 'sanctum');

        $this->department = Department::factory()->create([
            'name'      => 'Test Department',
            'is_active' => true,
        ]);
    }

    protected function employeePayload(array $overrides = []): array
    {
        return array_merge([
            'name'          => 'John Doe',
            'email'         => 'john.doe@example.com',
            'phone'         => '1234567890',
            'base_salary'   => 500,
            'mobile'        => '9876543210',
            'address'       => '123 Test Street',
            'start_date'    => '2024-01-01',
            'department_id' => $this->department->id,
            'is_active'     => true,
            'notes'         => 'Test employee',
        ], $overrides);
    }
}
