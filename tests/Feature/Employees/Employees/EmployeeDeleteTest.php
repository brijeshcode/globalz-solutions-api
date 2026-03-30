<?php

use App\Models\Employees\Employee;
use Tests\Feature\Employees\Employees\Concerns\HasEmployeeSetup;

uses(HasEmployeeSetup::class);

beforeEach(function () {
    $this->setUpEmployees();
});

it('soft deletes an employee', function () {
    $employee = Employee::factory()->create(['department_id' => $this->department->id]);

    $this->deleteJson(route('employees.destroy', $employee))
        ->assertStatus(204);

    $this->assertSoftDeleted('employees', ['id' => $employee->id]);
});

it('lists trashed employees', function () {
    $employee = Employee::factory()->create(['department_id' => $this->department->id]);
    $employee->delete();

    $this->getJson(route('employees.trashed'))
        ->assertOk()
        ->assertJson(['message' => 'Trashed employees retrieved successfully']);
});

it('restores a trashed employee', function () {
    $employee = Employee::factory()->create(['department_id' => $this->department->id]);
    $employee->delete();

    $this->patchJson(route('employees.restore', $employee->id))
        ->assertOk()
        ->assertJson(['message' => 'Employee restored successfully']);

    $this->assertDatabaseHas('employees', ['id' => $employee->id, 'deleted_at' => null]);
});

it('permanently deletes a trashed employee', function () {
    $employee = Employee::factory()->create(['department_id' => $this->department->id]);
    $employee->delete();

    $this->deleteJson(route('employees.force-delete', $employee->id))
        ->assertStatus(204);

    $this->assertDatabaseMissing('employees', ['id' => $employee->id]);
});
