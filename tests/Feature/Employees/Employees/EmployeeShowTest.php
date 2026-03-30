<?php

use App\Models\Employees\Employee;
use Tests\Feature\Employees\Employees\Concerns\HasEmployeeSetup;

uses(HasEmployeeSetup::class);

beforeEach(function () {
    $this->setUpEmployees();
});

it('shows an employee', function () {
    $employee = Employee::factory()->create([
        'department_id' => $this->department->id,
        'code'          => 'EMP002',
        'name'          => 'Jane Smith',
    ]);

    $this->getJson(route('employees.show', $employee))
        ->assertOk()
        ->assertJson(['message' => 'Employee retrieved successfully'])
        ->assertJsonStructure(['data' => ['id', 'code', 'name', 'department', 'is_active', 'created_at']]);
});
