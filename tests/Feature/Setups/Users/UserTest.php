<?php

use App\Models\Employees\Employee;
use App\Models\Setups\Employees\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses()->group('api', 'setup', 'setup.users', 'users');
uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'sanctum');
    
    $this->department = Department::factory()->create([
        'name' => 'Test Department',
        'is_active' => true
    ]);
});

describe('Users API', function () {
    it('can list users', function () {
        User::factory()->count(3)->create();

        $response = $this->getJson(route('setups.users.index'));

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'email',
                        'role',
                        'is_active',
                        'employee',
                        'created_by',
                        'updated_by',
                        'created_at',
                        'updated_at',
                    ]
                ],
                'pagination'
            ]);
    });

    it('can create a user', function () {
        $data = [
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'salesman',
            'is_active' => true,
        ];

        $response = $this->postJson(route('setups.users.store'), $data);

        $response->assertCreated()
            ->assertJson([
                'message' => 'User created successfully'
            ])
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'name',
                    'email',
                    'role',
                    'is_active',
                ]
            ]);

        $this->assertDatabaseHas('users', [
            'name' => 'John Doe',
            'email' => 'john.doe@example.com',
            'role' => 'salesman',
            'is_active' => true,
        ]);

        // Verify password is hashed
        $user = User::where('email', 'john.doe@example.com')->first();
        expect(Hash::check('password123', $user->password))->toBe(true);
    });

    it('can create user with employee assignment', function () {
        $employee = Employee::factory()->create([
            'department_id' => $this->department->id,
            'user_id' => null
        ]);

        $data = [
            'name' => 'Jane Smith',
            'email' => 'jane.smith@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'salesman',
            'is_active' => true,
            'employee_id' => $employee->id,
        ];

        $response = $this->postJson(route('setups.users.store'), $data);

        $response->assertCreated();

        // Verify employee is assigned to user
        $employee->refresh();
        $user = User::where('email', 'jane.smith@example.com')->first();
        expect($employee->user_id)->toBe($user->id);
    });

    it('can show a user', function () {
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com'
        ]);

        $response = $this->getJson(route('setups.users.show', $user));

        $response->assertOk()
            ->assertJson([
                'message' => 'User retrieved successfully',
                'data' => [
                    'id' => $user->id,
                    'name' => 'Test User',
                    'email' => 'test@example.com',
                ]
            ]);
    });

    it('can update a user', function () {
        $user = User::factory()->create([
            'name' => 'Original Name',
            'email' => 'original@example.com',
            'role' => 'salesman'
        ]);

        $data = [
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'role' => 'admin',
            'is_active' => false,
        ];

        $response = $this->putJson(route('setups.users.update', $user), $data);

        $response->assertOk()
            ->assertJson([
                'message' => 'User updated successfully',
                'data' => [
                    'name' => 'Updated Name',
                    'email' => 'updated@example.com',
                    'role' => 'admin',
                    'is_active' => false,
                ]
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated Name',
            'email' => 'updated@example.com',
            'role' => 'admin',
            'is_active' => false,
        ]);
    });

    it('can update user password', function () {
        $user = User::factory()->create();

        $data = [
            'name' => $user->name,
            'email' => $user->email,
            'password' => 'newpassword123',
            'password_confirmation' => 'newpassword123',
            'role' => $user->role,
        ]; 

        $response = $this->putJson(route('setups.users.update', $user), $data);

        $response->assertOk();

        // Verify password is updated and hashed
        $user->refresh();
        expect(Hash::check('newpassword123', $user->password))->toBe(true);
    });

    it('can update user with employee assignment', function () {
        $user = User::factory()->create();
        $employee = Employee::factory()->create([
            'department_id' => $this->department->id,
            'user_id' => null
        ]);

        $data = [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'employee_id' => $employee->id,
        ];

        $response = $this->putJson(route('setups.users.update', $user), $data);

        $response->assertOk();

        // Verify employee is assigned
        $employee->refresh();
        expect($employee->user_id)->toBe($user->id);
    });

    it('can update user and reassign employee', function () {
        $user = User::factory()->create();
        $oldEmployee = Employee::factory()->create([
            'department_id' => $this->department->id,
            'user_id' => $user->id
        ]);
        $newEmployee = Employee::factory()->create([
            'department_id' => $this->department->id,
            'user_id' => null
        ]);

        $data = [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'employee_id' => $newEmployee->id,
        ];

        $response = $this->putJson(route('setups.users.update', $user), $data);

        $response->assertOk();

        // Verify old employee is unassigned and new employee is assigned
        $oldEmployee->refresh();
        $newEmployee->refresh();
        expect($oldEmployee->user_id)->toBe(null);
        expect($newEmployee->user_id)->toBe($user->id);
    });

    it('can update user and unassign employee', function () {
        $user = User::factory()->create();
        $employee = Employee::factory()->create([
            'department_id' => $this->department->id,
            'user_id' => $user->id
        ]);

        $data = [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role,
            'employee_id' => null,
        ];

        $response = $this->putJson(route('setups.users.update', $user), $data);

        $response->assertOk();

        // Verify employee is unassigned
        $employee->refresh();
        expect($employee->user_id)->toBe(null);
    });

    it('can delete a user', function () {
        $user = User::factory()->create();

        $response = $this->deleteJson(route('setups.users.destroy', $user));

        $response->assertNoContent();
        $this->assertSoftDeleted('users', ['id' => $user->id]);
    });

    it('unassigns employee when deleting user', function () {
        $user = User::factory()->create();
        $employee = Employee::factory()->create([
            'department_id' => $this->department->id,
            'user_id' => $user->id
        ]);

        $response = $this->deleteJson(route('setups.users.destroy', $user));

        $response->assertNoContent();

        // Verify employee is unassigned
        $employee->refresh();
        expect($employee->user_id)->toBe(null);
    });

    it('validates required fields when creating', function () {
        $response = $this->postJson(route('setups.users.store'), []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email', 'password', 'role']);
    });

    it('validates unique email when creating', function () {
        $existingUser = User::factory()->create();

        $response = $this->postJson(route('setups.users.store'), [
            'name' => 'Test User',
            'email' => $existingUser->email,
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'salesman',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    });

    it('validates valid role when creating', function () {
        $response = $this->postJson(route('setups.users.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'invalid_role',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['role']);
    });

    it('validates password confirmation when creating', function () {
        $response = $this->postJson(route('setups.users.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'different_password',
            'role' => 'salesman',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    });

    it('validates minimum password length when creating', function () {
        $response = $this->postJson(route('setups.users.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => '123',
            'password_confirmation' => '123',
            'role' => 'salesman',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    });

    it('validates employee exists when assigning', function () {
        $response = $this->postJson(route('setups.users.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'salesman',
            'employee_id' => 99999,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['employee_id']);
    });

    it('validates employee is not already assigned when creating', function () {
        $assignedEmployee = Employee::factory()->create([
            'department_id' => $this->department->id,
            'user_id' => $this->user->id
        ]);

        $response = $this->postJson(route('setups.users.store'), [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'salesman',
            'employee_id' => $assignedEmployee->id,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['employee_id']);
    });

    it('validates unique email when updating', function () {
        $user1 = User::factory()->create();
        $user2 = User::factory()->create();

        $response = $this->putJson(route('setups.users.update', $user1), [
            'name' => $user1->name,
            'email' => $user2->email,
            'role' => $user1->role,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['email']);
    });

    it('allows updating user with its own email', function () {
        $user = User::factory()->create(['email' => 'test@example.com']);

        $response = $this->putJson(route('setups.users.update', $user), [
            'name' => 'Updated Name',
            'email' => 'test@example.com', // Same email should be allowed
            'role' => $user->role,
        ]);

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'name' => 'Updated Name',
                    'email' => 'test@example.com',
                ]
            ]);
    });

    it('can search users', function () {
        User::factory()->create(['name' => 'Searchable User']);
        User::factory()->create(['name' => 'Another User']);

        $response = $this->getJson(route('setups.users.index', ['search' => 'Searchable']));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['name'])->toBe('Searchable User');
    });

    it('can search users by email', function () {
        User::factory()->create(['email' => 'searchable@example.com']);
        User::factory()->create(['email' => 'another@example.com']);

        $response = $this->getJson(route('setups.users.index', ['search' => 'searchable']));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['email'])->toBe('searchable@example.com');
    });

    it('can filter by active status', function () {
        User::factory()->create(['is_active' => true]);
        User::factory()->create(['is_active' => false]);

        $response = $this->getJson(route('setups.users.index', ['is_active' => true]));

        $response->assertOk();
        $data = $response->json('data');
        
        foreach ($data as $user) {
            expect($user['is_active'])->toBe(true);
        }
    });

    it('can filter by role', function () {
        User::factory()->create(['role' => 'admin']);
        User::factory()->create(['role' => 'salesman']);

        $response = $this->getJson(route('setups.users.index', ['role' => 'admin']));

        $response->assertOk();
        $data = $response->json('data');
        
        foreach ($data as $user) {
            expect($user['role'])->toBe('admin');
        }
    });

    it('can sort users', function () {
        User::factory()->create(['name' => 'B User']);
        User::factory()->create(['name' => 'A User']);

        $response = $this->getJson(route('setups.users.index', [
            'sort_by' => 'name',
            'sort_direction' => 'asc'
        ]));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data[0]['name'])->toBe('A User');
        expect($data[1]['name'])->toBe('B User');
    });

    it('returns 404 for non-existent user', function () {
        $response = $this->getJson(route('setups.users.show', 999));

        $response->assertNotFound();
    });

    it('can list trashed users', function () {
        $user = User::factory()->create();
        $user->delete();

        $response = $this->getJson(route('setups.users.trashed'));

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'email',
                        'role',
                        'is_active',
                    ]
                ],
                'pagination'
            ]);

        expect($response->json('data'))->toHaveCount(1);
    });

    it('can restore a trashed user', function () {
        $user = User::factory()->create();
        $user->delete();

        $response = $this->patchJson(route('setups.users.restore', $user->id));

        $response->assertOk()
            ->assertJson([
                'message' => 'User restored successfully'
            ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'deleted_at' => null,
        ]);
    });

    it('can force delete a trashed user', function () {
        $user = User::factory()->create();
        $user->delete();

        $response = $this->deleteJson(route('setups.users.force-delete', $user->id));

        $response->assertNoContent();
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    });

    it('unassigns employee when force deleting user', function () {
        $user = User::factory()->create();
        $employee = Employee::factory()->create([
            'department_id' => $this->department->id,
            'user_id' => $user->id
        ]);
        $user->delete();

        $response = $this->deleteJson(route('setups.users.force-delete', $user->id));

        $response->assertNoContent();

        // Verify employee is unassigned
        $employee->refresh();
        expect($employee->user_id)->toBe(null);
    });

    it('returns 404 when trying to restore non-existent trashed user', function () {
        $response = $this->patchJson(route('setups.users.restore', 999));

        $response->assertNotFound();
    });

    it('returns 404 when trying to force delete non-existent trashed user', function () {
        $response = $this->deleteJson(route('setups.users.force-delete', 999));

        $response->assertNotFound();
    });

    it('can get unassigned employees', function () {
        $assignedEmployee = Employee::factory()->create([
            'department_id' => $this->department->id,
            'user_id' => $this->user->id
        ]);
        $unassignedEmployee = Employee::factory()->create([
            'department_id' => $this->department->id,
            'user_id' => null
        ]);

        $response = $this->getJson(route('setups.users.unassigned-employees'));

        $response->assertOk()
            ->assertJson([
                'message' => 'Unassigned employees retrieved successfully'
            ]);

        $data = $response->json('data');
        expect($data)->toHaveCount(1);
        expect($data[0]['id'])->toBe($unassignedEmployee->id);
    });

    it('can search unassigned employees', function () {
        Employee::factory()->create([
            'department_id' => $this->department->id,
            'name' => 'Searchable Employee',
            'user_id' => null
        ]);
        Employee::factory()->create([
            'department_id' => $this->department->id,
            'name' => 'Another Employee',
            'user_id' => null
        ]);

        $response = $this->getJson(route('setups.users.unassigned-employees', ['search' => 'Searchable']));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['name'])->toBe('Searchable Employee');
    });

    it('only returns active employees in unassigned list', function () {
        Employee::factory()->create([
            'department_id' => $this->department->id,
            'user_id' => null,
            'is_active' => true
        ]);
        Employee::factory()->create([
            'department_id' => $this->department->id,
            'user_id' => null,
            'is_active' => false
        ]);

        $response = $this->getJson(route('setups.users.unassigned-employees'));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
    });

    it('sets created_by when creating user', function () {
        $data = [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'role' => 'salesman',
            'is_active' => true,
        ];

        $response = $this->postJson(route('setups.users.store'), $data);

        $response->assertCreated();
        
        $this->assertDatabaseHas('users', [
            'name' => 'Test User',
            'created_by' => $this->user->id,
        ]);
    });

    it('sets updated_by when updating user', function () {
        $user = User::factory()->create();
        $data = [
            'name' => 'Updated User',
            'email' => $user->email,
            'role' => $user->role,
        ];

        $response = $this->putJson(route('setups.users.update', $user), $data);

        $response->assertOk();
        
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'Updated User',
            'updated_by' => $this->user->id,
        ]);
    });

    it('does not update password when not provided', function () {
        $user = User::factory()->create();
        $originalPassword = $user->password;

        $data = [
            'name' => 'Updated User',
            'email' => $user->email,
            'role' => $user->role,
        ];

        $response = $this->putJson(route('setups.users.update', $user), $data);

        $response->assertOk();
        
        $user->refresh();
        expect($user->password)->toBe($originalPassword);
    });

    it('does not update password when empty string provided', function () {
        $user = User::factory()->create();
        $originalPassword = $user->password;

        $data = [
            'name' => 'Updated User',
            'email' => $user->email,
            'role' => $user->role,
            'password' => '',
            'password_confirmation' => '',
        ];

        $response = $this->putJson(route('setups.users.update', $user), $data);

        $response->assertOk();
        
        $user->refresh();
        expect($user->password)->toBe($originalPassword);
    });
});