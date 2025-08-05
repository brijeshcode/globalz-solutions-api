<?php

use App\Models\Group;
use App\Models\User;

uses()->group('api', 'setup', 'setup.groups', 'groups');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'sanctum');
});

describe('Groups API', function () {
    it('can list groups', function () {
        Group::factory()->count(3)->create();

        $response = $this->getJson(route('setups.groups.index'));

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

        $response = $this->postJson(route('setups.groups.store'), $data);

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

        $this->assertDatabaseHas('groups', [
            'name' => 'Test Group',
            'description' => 'Test Description',
        ]);
    });

    it('can show a group', function () {
        $group = Group::factory()->create();

        $response = $this->getJson(route('setups.groups.show', $group));

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $group->id,
                    'name' => $group->name,
                ]
            ]);
    });

    it('can update a group', function () {
        $group = Group::factory()->create();
        $data = [
            'name' => 'Updated Group',
            'description' => 'Updated Description',
        ];

        $response = $this->putJson(route('setups.groups.update', $group), $data);

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'name' => 'Updated Group',
                    'description' => 'Updated Description',
                ]
            ]);

        $this->assertDatabaseHas('groups', [
            'id' => $group->id,
            'name' => 'Updated Group',
        ]);
    });

    it('can delete a group', function () {
        $group = Group::factory()->create();

        $response = $this->deleteJson(route('setups.groups.destroy', $group));

        $response->assertNoContent();
        $this->assertSoftDeleted('groups', ['id' => $group->id]);
    });

    it('validates required fields when creating', function () {
        $response = $this->postJson(route('setups.groups.store'), []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('validates unique name when creating', function () {
        $existingGroup = Group::factory()->create();

        $response = $this->postJson(route('setups.groups.store'), [
            'name' => $existingGroup->name,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('validates unique name when updating', function () {
        $group1 = Group::factory()->create();
        $group2 = Group::factory()->create();

        $response = $this->putJson(route('setups.groups.update', $group1), [
            'name' => $group2->name,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('can search groups', function () {
        Group::factory()->create(['name' => 'Searchable Group']);
        Group::factory()->create(['name' => 'Another Group']);

        $response = $this->getJson(route('setups.groups.index', ['search' => 'Searchable']));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['name'])->toBe('Searchable Group');
    });

    it('can filter by active status', function () {
        Group::factory()->active()->create();
        Group::factory()->inactive()->create();

        $response = $this->getJson(route('setups.groups.index', ['is_active' => true]));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['is_active'])->toBe(true);
    });

    it('can sort groups', function () {
        Group::factory()->create(['name' => 'B Group']);
        Group::factory()->create(['name' => 'A Group']);

        $response = $this->getJson(route('setups.groups.index', [
            'sort_by' => 'name',
            'sort_direction' => 'asc'
        ]));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data[0]['name'])->toBe('A Group');
        expect($data[1]['name'])->toBe('B Group');
    });

    it('returns 404 for non-existent group', function () {
        $response = $this->getJson(route('setups.groups.show', 999));

        $response->assertNotFound();
    });

    it('can list trashed groups', function () {
        $group = Group::factory()->create();
        $group->delete();

        $response = $this->getJson(route('setups.groups.trashed'));

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
        $group = Group::factory()->create();
        $group->delete();

        $response = $this->patchJson(route('setups.groups.restore', $group->id));

        $response->assertOk();
        $this->assertDatabaseHas('groups', [
            'id' => $group->id,
            'deleted_at' => null,
        ]);
    });

    it('can force delete a trashed group', function () {
        $group = Group::factory()->create();
        $group->delete();

        $response = $this->deleteJson(route('setups.groups.force-delete', $group->id));

        $response->assertNoContent();
        $this->assertDatabaseMissing('groups', ['id' => $group->id]);
    });

    it('returns 404 when trying to restore non-existent trashed group', function () {
        $response = $this->patchJson(route('setups.groups.restore', 999));

        $response->assertNotFound();
    });

    it('returns 404 when trying to force delete non-existent trashed group', function () {
        $response = $this->deleteJson(route('setups.groups.force-delete', 999));

        $response->assertNotFound();
    });
});