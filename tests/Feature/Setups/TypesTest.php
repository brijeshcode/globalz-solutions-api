<?php

use App\Models\Type;
use App\Models\User;

uses()->group('api', 'setup', 'setup.types', 'types');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'sanctum');
});

describe('Types API', function () {
    it('can list types', function () {
        Type::factory()->count(3)->create();

        $response = $this->getJson(route('setups.types.index'));

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

    it('can create a type', function () {
        $data = [
            'name' => 'Test Type',
            'description' => 'Test Description',
            'is_active' => true,
        ];

        $response = $this->postJson(route('setups.types.store'), $data);

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

        $this->assertDatabaseHas('types', [
            'name' => 'Test Type',
            'description' => 'Test Description',
        ]);
    });

    it('can show a type', function () {
        $type = Type::factory()->create();

        $response = $this->getJson(route('setups.types.show', $type));

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $type->id,
                    'name' => $type->name,
                ]
            ]);
    });

    it('can update a type', function () {
        $type = Type::factory()->create();
        $data = [
            'name' => 'Updated Type',
            'description' => 'Updated Description',
        ];

        $response = $this->putJson(route('setups.types.update', $type), $data);

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'name' => 'Updated Type',
                    'description' => 'Updated Description',
                ]
            ]);

        $this->assertDatabaseHas('types', [
            'id' => $type->id,
            'name' => 'Updated Type',
        ]);
    });

    it('can delete a type', function () {
        $type = Type::factory()->create();

        $response = $this->deleteJson(route('setups.types.destroy', $type));

        $response->assertNoContent();
        $this->assertSoftDeleted('types', ['id' => $type->id]);
    });

    it('validates required fields when creating', function () {
        $response = $this->postJson(route('setups.types.store'), []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('validates unique name when creating', function () {
        $existingType = Type::factory()->create();

        $response = $this->postJson(route('setups.types.store'), [
            'name' => $existingType->name,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('validates unique name when updating', function () {
        $type1 = Type::factory()->create();
        $type2 = Type::factory()->create();

        $response = $this->putJson(route('setups.types.update', $type1), [
            'name' => $type2->name,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('can search types', function () {
        Type::factory()->create(['name' => 'Searchable Type']);
        Type::factory()->create(['name' => 'Another Type']);

        $response = $this->getJson(route('setups.types.index', ['search' => 'Searchable']));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['name'])->toBe('Searchable Type');
    });

    it('can filter by active status', function () {
        Type::factory()->active()->create();
        Type::factory()->inactive()->create();

        $response = $this->getJson(route('setups.types.index', ['is_active' => true]));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['is_active'])->toBe(true);
    });

    it('can sort types', function () {
        Type::factory()->create(['name' => 'B Type']);
        Type::factory()->create(['name' => 'A Type']);

        $response = $this->getJson(route('setups.types.index', [
            'sort_by' => 'name',
            'sort_direction' => 'asc'
        ]));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data[0]['name'])->toBe('A Type');
        expect($data[1]['name'])->toBe('B Type');
    });

    it('returns 404 for non-existent type', function () {
        $response = $this->getJson(route('setups.types.show', 999));

        $response->assertNotFound();
    });

    it('can list trashed types', function () {
        $type = Type::factory()->create();
        $type->delete();

        $response = $this->getJson(route('setups.types.trashed'));

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

    it('can restore a trashed type', function () {
        $type = Type::factory()->create();
        $type->delete();

        $response = $this->patchJson(route('setups.types.restore', $type->id));

        $response->assertOk();
        $this->assertDatabaseHas('types', [
            'id' => $type->id,
            'deleted_at' => null,
        ]);
    });

    it('can force delete a trashed type', function () {
        $type = Type::factory()->create();
        $type->delete();

        $response = $this->deleteJson(route('setups.types.force-delete', $type->id));

        $response->assertNoContent();
        $this->assertDatabaseMissing('types', ['id' => $type->id]);
    });

    it('returns 404 when trying to restore non-existent trashed type', function () {
        $response = $this->patchJson(route('setups.types.restore', 999));

        $response->assertNotFound();
    });

    it('returns 404 when trying to force delete non-existent trashed type', function () {
        $response = $this->deleteJson(route('setups.types.force-delete', 999));

        $response->assertNotFound();
    });
});