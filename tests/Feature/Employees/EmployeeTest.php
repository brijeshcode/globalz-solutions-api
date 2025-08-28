<?php

use App\Models\Employees\Employee;
use App\Models\Setups\Employees\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses()->group('api', 'setup', 'setup.employees', 'employees');
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'sanctum');
    
    $this->department = Department::factory()->create([
        'name' => 'Test Department',
        'is_active' => true
    ]);
});

test('can get employees list', function () {
    Employee::factory()->count(3)->create([
        'department_id' => $this->department->id
    ]);

    $response = $this->getJson(route('employees.index'));

    $response->assertStatus(200)
        ->assertJsonStructure([
            'message',
            'data' => [
                '*' => [
                    'id',
                    'code',
                    'name',
                    'email',
                    'department',
                    'is_active',
                    'created_at'
                ],
            ],
            'pagination'
        ]);
});

test('can create employee', function () {
    $employeeData = [
        'code' => 'EMP001',
        'name' => 'John Doe',
        'email' => 'john.doe@example.com',
        'phone' => '1234567890',
        'mobile' => '9876543210',
        'address' => '123 Test Street',
        'start_date' => '2024-01-01',
        'department_id' => $this->department->id,
        'is_active' => true,
        'notes' => 'Test employee'
    ];

    $response = $this->postJson(route('employees.store'), $employeeData);

    $response->assertStatus(201)
        ->assertJson([
            'message' => 'Employee created successfully'
        ])
        ->assertJsonStructure([
            'data' => [
                'id',
                'code',
                'name',
                'email',
                'department',
                'is_active'
            ]
        ]);

    $this->assertDatabaseHas('employees', [
        'code' => 'EMP001',
        'name' => 'John Doe',
        'email' => 'john.doe@example.com'
    ]);
});

test('can show employee', function () {
    $employee = Employee::factory()->create([
        'department_id' => $this->department->id,
        'code' => 'EMP002',
        'name' => 'Jane Smith'
    ]);

    $response = $this->getJson(route('employees.show', $employee));

    $response->assertStatus(200)
        ->assertJson([
            'message' => 'Employee retrieved successfully'
        ])
        ->assertJsonStructure([
            'data' => [
                'id',
                'code',
                'name',
                'department',
                'is_active',
                'created_at'
            ]
        ]);
});

test('can update employee', function () {
    $employee = Employee::factory()->create([
        'department_id' => $this->department->id,
        'name' => 'Original Name'
    ]);

    $updateData = [
        'code' => $employee->code,
        'name' => 'Updated Name',
        'email' => 'updated@example.com',
        'start_date' => $employee->start_date,
        'department_id' => $this->department->id,
        'is_active' => false
    ];

    $response = $this->putJson(route('employees.update', $employee), $updateData);

    $response->assertStatus(200)
        ->assertJson([
            'message' => 'Employee updated successfully'
        ]);

    $this->assertDatabaseHas('employees', [
        'id' => $employee->id,
        'name' => 'Updated Name',
        'email' => 'updated@example.com',
        'is_active' => false
    ]);
});

test('can delete employee', function () {
    $employee = Employee::factory()->create([
        'department_id' => $this->department->id
    ]);

    $response = $this->deleteJson(route('employees.destroy', $employee));

    $response->assertStatus(204);

    $this->assertSoftDeleted('employees', [
        'id' => $employee->id
    ]);
});

test('can get trashed employees', function () {
    $employee = Employee::factory()->create([
        'department_id' => $this->department->id
    ]);
    $employee->delete();

    $response = $this->getJson(route('employees.trashed'));

    $response->assertStatus(200)
        ->assertJson([
            'message' => 'Trashed employees retrieved successfully'
        ]);
});

test('can restore employee', function () {
    $employee = Employee::factory()->create([
        'department_id' => $this->department->id
    ]);
    $employee->delete();

    $response = $this->patchJson(route('employees.restore', $employee->id));

    $response->assertStatus(200)
        ->assertJson([
            'message' => 'Employee restored successfully'
        ]);

    $this->assertDatabaseHas('employees', [
        'id' => $employee->id,
        'deleted_at' => null
    ]);
});

test('can permanently delete employee', function () {
    $employee = Employee::factory()->create([
        'department_id' => $this->department->id
    ]);
    $employee->delete();

    $response = $this->deleteJson(route('employees.force-delete', $employee->id));

    $response->assertStatus(204);

    $this->assertDatabaseMissing('employees', [
        'id' => $employee->id
    ]);
});

test('validates required fields when creating employee', function () {
    $response = $this->postJson(route('employees.store'), []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['code', 'name', 'start_date', 'department_id']);
});

test('validates unique code when creating employee', function () {
    Employee::factory()->create([
        'code' => 'EMP001',
        'department_id' => $this->department->id
    ]);

    $response = $this->postJson(route('employees.store'), [
        'code' => 'EMP001',
        'name' => 'Test Employee',
        'start_date' => '2024-01-01',
        'department_id' => $this->department->id
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['code']);
});

test('validates unique email when creating employee', function () {
    Employee::factory()->create([
        'email' => 'test@example.com',
        'department_id' => $this->department->id
    ]);

    $response = $this->postJson(route('employees.store'), [
        'code' => 'EMP001',
        'name' => 'Test Employee',
        'email' => 'test@example.com',
        'start_date' => '2024-01-01',
        'department_id' => $this->department->id
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

test('can filter employees by active status', function () {
    Employee::factory()->create([
        'department_id' => $this->department->id,
        'is_active' => true
    ]);
    Employee::factory()->create([
        'department_id' => $this->department->id,
        'is_active' => false
    ]);

    $response = $this->getJson(route('employees.index', ['is_active' => 1]));

    $response->assertStatus(200);
    $employees = $response->json('data');
    
    foreach ($employees as $employee) {
        expect($employee['is_active'])->toBe(true);
    }
});

test('can filter employees by department', function () {
    $otherDepartment = Department::factory()->create();
    
    Employee::factory()->create([
        'department_id' => $this->department->id
    ]);
    Employee::factory()->create([
        'department_id' => $otherDepartment->id
    ]);

    $response = $this->getJson(route('employees.index', ['department_id' => $this->department->id]));

    $response->assertStatus(200);
    $employees = $response->json('data');
    
    foreach ($employees as $employee) {
        expect($employee['department_id'])->toBe($this->department->id);
    }
});
