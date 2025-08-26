<?php

use App\Models\Setups\Customers\CustomerGroup;
use App\Models\User;

uses()->group('api', 'setup', 'setup.customers', 'setup.customers.groups', 'customer_groups');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'sanctum');
});

describe('Groups API', function () {
    it('can list groups', function () {
        CustomerGroup::factory()->count(3)->create();

        $response = $this->getJson(route('setups.customers.groups.index'));

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

    it('can create a group', function () {
        $data = [
            'name' => 'Test Group',
            'description' => 'Test Description',
            'is_active' => true,
        ];

        $response = $this->postJson(route('setups.customers.groups.store'), $data);

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

        $this->assertDatabaseHas('Customer_groups', [
            'name' => 'Test Group',
            'description' => 'Test Description',
        ]);
    });

    it('can show a group', function () {
        $group = CustomerGroup::factory()->create();

        $response = $this->getJson(route('setups.customers.groups.show', $group->id));

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $group->id,
                    'name' => $group->name,
                ]
            ]);
    });

    it('can update a group', function () {
        $group = CustomerGroup::factory()->create();
        $data = [
            'name' => 'Updated Group',
            'description' => 'Updated Description',
        ];

        $response = $this->putJson(route('setups.customers.groups.update', $group), $data);

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'name' => 'Updated Group',
                    'description' => 'Updated Description',
                ]
            ]);

        $this->assertDatabaseHas('Customer_groups', [
            'id' => $group->id,
            'name' => 'Updated Group',
        ]);
    });

    it('can delete a group', function () {
        $group = CustomerGroup::factory()->create();

        $response = $this->deleteJson(route('setups.customers.groups.destroy', $group));

        $response->assertNoContent();
        $this->assertSoftDeleted('Customer_groups', ['id' => $group->id]);
    });

    it('validates required fields when creating', function () {
        $response = $this->postJson(route('setups.customers.groups.store'), []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('validates unique name when creating', function () {
        $existingGroup = CustomerGroup::factory()->create();

        $response = $this->postJson(route('setups.customers.groups.store'), [
            'name' => $existingGroup->name,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('validates unique name when updating', function () {
        $group1 = CustomerGroup::factory()->create();
        $group2 = CustomerGroup::factory()->create();

        $response = $this->putJson(route('setups.customers.groups.update', $group1), [
            'name' => $group2->name,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('allows updating group with its own name', function () {
        $group = CustomerGroup::factory()->create(['name' => 'Test Group']);

        $response = $this->putJson(route('setups.customers.groups.update', $group), [
            'name' => 'Test Group', // Same name should be allowed
            'description' => 'Updated description',
        ]);

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'name' => 'Test Group',
                    'description' => 'Updated description',
                ]
            ]);
    });

    it('can search groups', function () {
        CustomerGroup::factory()->create(['name' => 'Searchable Group']);
        CustomerGroup::factory()->create(['name' => 'Another Group']);

        $response = $this->getJson(route('setups.customers.groups.index', ['search' => 'Searchable']));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['name'])->toBe('Searchable Group');
    });

    it('can filter by active status', function () {
        CustomerGroup::factory()->active()->create();
        CustomerGroup::factory()->inactive()->create();

        $response = $this->getJson(route('setups.customers.groups.index', ['is_active' => true]));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['is_active'])->toBe(true);
    });

    it('can sort groups', function () {
        CustomerGroup::factory()->create(['name' => 'B Group']);
        CustomerGroup::factory()->create(['name' => 'A Group']);

        $response = $this->getJson(route('setups.customers.groups.index', [
            'sort_by' => 'name',
            'sort_direction' => 'asc'
        ]));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data[0]['name'])->toBe('A Group');
        expect($data[1]['name'])->toBe('B Group');
    });

    it('returns 404 for non-existent group', function () {
        $response = $this->getJson(route('setups.customers.groups.show', 999));

        $response->assertNotFound();
    });

    it('can list trashed groups', function () {
        $group = CustomerGroup::factory()->create();
        $group->delete();

        $response = $this->getJson(route('setups.customers.groups.trashed'));

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

    it('can restore a trashed group', function () {
        $group = CustomerGroup::factory()->create();
        $group->delete();

        $response = $this->patchJson(route('setups.customers.groups.restore', $group->id));

        $response->assertOk();
        $this->assertDatabaseHas('Customer_groups', [
            'id' => $group->id,
            'deleted_at' => null,
        ]);
    });

    it('can force delete a trashed group', function () {
        $group = CustomerGroup::factory()->create();
        $group->delete();

        $response = $this->deleteJson(route('setups.customers.groups.force-delete', $group->id));

        $response->assertNoContent();
        $this->assertDatabaseMissing('Customer_groups', ['id' => $group->id]);
    });

    it('returns 404 when trying to restore non-existent trashed group', function () {
        $response = $this->patchJson(route('setups.customers.groups.restore', 999));

        $response->assertNotFound();
    });

    it('returns 404 when trying to force delete non-existent trashed group', function () {
        $response = $this->deleteJson(route('setups.customers.groups.force-delete', 999));

        $response->assertNotFound();
    });
});