<?php

use App\Models\Setups\Employees\Department;
use App\Models\User;

uses()->group('api', 'setup', 'setup.employees', 'setup.employees.departments', 'employee_departments');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'sanctum');
});

describe('Employee Deparments API', function () {
    it('can list employee departments', function () {
        Department::factory()->count(3)->create();

        $response = $this->getJson(route('setups.employees.departments.index'));

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'description',
                        'is_active',
                        'created_by',
                        'updated_by',
                        'created_at',
                        'updated_at',
                    ]
                ],
                'pagination'
            ]);
    });

    it('can create a employee deparment', function () {
        $data = [
            'name' => 'Test Employee Deparment',
            'description' => 'Test Description',
            'is_active' => true,
        ];

        $response = $this->postJson(route('setups.employees.departments.store'), $data);

        $response->assertCreated()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'name',
                    'description',
                    'is_active',
                ]
            ]);

        $this->assertDatabaseHas('departments', [
            'name' => 'Test Employee Deparment',
            'description' => 'Test Description',
        ]);
    });

    it('can show a employee deparment', function () {
        $deparment = Department::factory()->create();

        $response = $this->getJson(route('setups.employees.departments.show', $deparment));

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $deparment->id,
                    'name' => $deparment->name,
                ]
            ]);
    });

    it('can update a employee deparment', function () {
        $deparment = Department::factory()->create();
        $data = [
            'name' => 'Updated Employee Deparment',
            'description' => 'Updated Description',
        ];

        $response = $this->putJson(route('setups.employees.departments.update', $deparment), $data);

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'name' => 'Updated Employee Deparment',
                    'description' => 'Updated Description',
                ]
            ]);

        $this->assertDatabaseHas('departments', [
            'id' => $deparment->id,
            'name' => 'Updated Employee Deparment',
        ]);
    });

    it('can delete a employee deparment', function () {
        $deparment = Department::factory()->create();

        $response = $this->deleteJson(route('setups.employees.departments.destroy', $deparment));

        $response->assertNoContent();
        $this->assertSoftDeleted('departments', ['id' => $deparment->id]);
    });

    it('validates required fields when creating', function () {
        $response = $this->postJson(route('setups.employees.departments.store'), []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('validates unique name when creating', function () {
        $existingDepartment = Department::factory()->create();

        $response = $this->postJson(route('setups.employees.departments.store'), [
            'name' => $existingDepartment->name,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('validates unique name when updating', function () {
        $deparment1 = Department::factory()->create();
        $deparment2 = Department::factory()->create();

        $response = $this->putJson(route('setups.employees.departments.update', $deparment1), [
            'name' => $deparment2->name,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('allows updating employee deparment with its own name', function () {
        $deparment = Department::factory()->create(['name' => 'Test Employee Deparment']);

        $response = $this->putJson(route('setups.employees.departments.update', $deparment), [
            'name' => 'Test Employee Deparment', // Same name should be allowed
            'description' => 'Updated description',
        ]);

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'name' => 'Test Employee Deparment',
                    'description' => 'Updated description',
                ]
            ]);
    });

    it('can search employee departments', function () {
        Department::factory()->create(['name' => 'Searchable Employee Deparment']);
        Department::factory()->create(['name' => 'Another Employee Deparment']);

        $response = $this->getJson(route('setups.employees.departments.index', ['search' => 'Searchable']));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['name'])->toBe('Searchable Employee Deparment');
    });

    it('can filter by active status', function () {
        Department::factory()->active()->create();
        Department::factory()->inactive()->create();

        $response = $this->getJson(route('setups.employees.departments.index', ['is_active' => true]));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['is_active'])->toBe(true);
    });

    it('can sort employee departments', function () {
        Department::factory()->create(['name' => 'B Employee Deparment']);
        Department::factory()->create(['name' => 'A Employee Deparment']);

        $response = $this->getJson(route('setups.employees.departments.index', [
            'sort_by' => 'name',
            'sort_direction' => 'asc'
        ]));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data[0]['name'])->toBe('A Employee Deparment');
        expect($data[1]['name'])->toBe('B Employee Deparment');
    });

    it('returns 404 for non-existent employee deparment', function () {
        $response = $this->getJson(route('setups.employees.departments.show', 999));

        $response->assertNotFound();
    });

    it('can list trashed employee departments', function () {
        $deparment = Department::factory()->create();
        $deparment->delete();

        $response = $this->getJson(route('setups.employees.departments.trashed'));

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'description',
                        'is_active',
                    ]
                ],
                'pagination'
            ]);

        expect($response->json('data'))->toHaveCount(1);
    });

    it('can restore a trashed employee deparment', function () {
        $deparment = Department::factory()->create();
        $deparment->delete();

        $response = $this->patchJson(route('setups.employees.departments.restore', $deparment->id));

        $response->assertOk();
        $this->assertDatabaseHas('departments', [
            'id' => $deparment->id,
            'deleted_at' => null,
        ]);
    });

    it('can force delete a trashed employee deparment', function () {
        $deparment = Department::factory()->create();
        $deparment->delete();

        $response = $this->deleteJson(route('setups.employees.departments.force-delete', $deparment->id));

        $response->assertNoContent();
        $this->assertDatabaseMissing('departments', ['id' => $deparment->id]);
    });

    it('returns 404 when trying to restore non-existent trashed employee deparment', function () {
        $response = $this->patchJson(route('setups.employees.departments.restore', 999));

        $response->assertNotFound();
    });

    it('returns 404 when trying to force delete non-existent trashed employee deparment', function () {
        $response = $this->deleteJson(route('setups.employees.departments.force-delete', 999));

        $response->assertNotFound();
    });

    it('can search in description field', function () {
        Department::factory()->create([
            'name' => 'Department One',
            'description' => 'Special retail description'
        ]);
        Department::factory()->create([
            'name' => 'Department Two', 
            'description' => 'Regular wholesale description'
        ]);

        $response = $this->getJson(route('setups.employees.departments.index', ['search' => 'retail']));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['description'])->toContain('retail');
    });

    it('sets created_by when creating employee deparment', function () {
        $data = [
            'name' => 'Test Employee Deparment',
            'description' => 'Test Description',
            'is_active' => true,
        ];

        $response = $this->postJson(route('setups.employees.departments.store'), $data);

        $response->assertCreated();
        
        $this->assertDatabaseHas('departments', [
            'name' => 'Test Employee Deparment',
            'created_by' => $this->user->id,
        ]);
    });

    it('sets updated_by when updating employee deparment', function () {
        $deparment = Department::factory()->create();
        $data = [
            'name' => 'Updated Employee Deparment',
            'description' => 'Updated Description',
        ];

        $response = $this->putJson(route('setups.employees.departments.update', $deparment), $data);

        $response->assertOk();
        
        $this->assertDatabaseHas('departments', [
            'id' => $deparment->id,
            'name' => 'Updated Employee Deparment',
            'updated_by' => $this->user->id,
        ]);
    });
});
