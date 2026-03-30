<?php

use App\Models\Employees\Employee;
use Tests\Feature\Employees\Employees\Concerns\HasEmployeeSetup;

uses(HasEmployeeSetup::class);

beforeEach(function () {
    $this->setUpEmployees();
});

it('creates an employee', function () {
    $this->postJson(route('employees.store'), $this->employeePayload())
        ->assertCreated()
        ->assertJson(['message' => 'Employee created successfully'])
        ->assertJsonStructure(['data' => ['id', 'code', 'name', 'email', 'department', 'is_active']]);

    $this->assertDatabaseHas('employees', ['name' => 'John Doe', 'email' => 'john.doe@example.com']);
});

it('requires name, start_date, and department_id', function () {
    $this->postJson(route('employees.store'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'start_date', 'department_id']);
});

it('validates unique email', function () {
    Employee::factory()->create([
        'email'         => 'test@example.com',
        'department_id' => $this->department->id,
    ]);

    $this->postJson(route('employees.store'), $this->employeePayload(['email' => 'test@example.com']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('validates unique code', function () {
    Employee::factory()->create(['code' => 'EMP001', 'department_id' => $this->department->id]);

    $this->postJson(route('employees.store'), $this->employeePayload(['code' => 'EMP001']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['code']);
})->skip();
