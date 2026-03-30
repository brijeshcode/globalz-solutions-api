<?php

use App\Models\Employees\Employee;
use Tests\Feature\Employees\Employees\Concerns\HasEmployeeSetup;

uses(HasEmployeeSetup::class);

beforeEach(function () {
    $this->setUpEmployees();
});

it('updates an employee', function () {
    $employee = Employee::factory()->create([
        'department_id' => $this->department->id,
        'name'          => 'Original Name',
    ]);

    $this->putJson(route('employees.update', $employee), [
        'code'          => $employee->code,
        'base_salary'   => 500,
        'name'          => 'Updated Name',
        'email'         => 'updated@example.com',
        'start_date'    => $employee->start_date,
        'department_id' => $this->department->id,
        'is_active'     => false,
    ])->assertOk()
      ->assertJson(['message' => 'Employee updated successfully']);

    $this->assertDatabaseHas('employees', [
        'id'        => $employee->id,
        'name'      => 'Updated Name',
        'email'     => 'updated@example.com',
        'is_active' => false,
    ]);
});
