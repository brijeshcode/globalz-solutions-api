<?php

use App\Models\Employees\Employee;
use App\Models\Setups\Employees\Department;
use Tests\Feature\Employees\Employees\Concerns\HasEmployeeSetup;

uses(HasEmployeeSetup::class);

beforeEach(function () {
    $this->setUpEmployees();
});

it('lists employees with correct structure', function () {
    Employee::factory()->count(3)->create(['department_id' => $this->department->id]);

    $this->getJson(route('employees.index'))
        ->assertOk()
        ->assertJsonStructure([
            'message',
            'data' => ['*' => ['id', 'code', 'name', 'email', 'department', 'is_active', 'created_at']],
            'pagination',
        ]);
});

it('filters by active status', function () {
    Employee::factory()->create(['department_id' => $this->department->id, 'is_active' => true]);
    Employee::factory()->create(['department_id' => $this->department->id, 'is_active' => false]);

    $employees = $this->getJson(route('employees.index', ['is_active' => 1]))
        ->assertOk()
        ->json('data');

    foreach ($employees as $employee) {
        expect($employee['is_active'])->toBe(true);
    }
});

it('filters by department', function () {
    $other = Department::factory()->create();

    Employee::factory()->create(['department_id' => $this->department->id]);
    Employee::factory()->create(['department_id' => $other->id]);

    $employees = $this->getJson(route('employees.index', ['department_id' => $this->department->id]))
        ->assertOk()
        ->json('data');

    foreach ($employees as $employee) {
        expect($employee['department_id'])->toBe($this->department->id);
    }
});
