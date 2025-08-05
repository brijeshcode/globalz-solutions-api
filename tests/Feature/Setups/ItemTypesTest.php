<?php

use App\Models\Setups\ItemType;
use App\Models\User;

uses()->group('api', 'setup', 'setup.types', 'item_types');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'sanctum');
});

describe('Types API', function () {
    it('can list types', function () {
        ItemType::factory()->count(3)->create();

        $response = $this->getJson(route('setups.items.types.index'));

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

        $response = $this->postJson(route('setups.items.types.store'), $data);

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

        $this->assertDatabaseHas('item_types', [
            'name' => 'Test Type',
            'description' => 'Test Description',
        ]);
    });

    it('can show a type', function () {
        $type = ItemType::factory()->create();

        $response = $this->getJson(route('setups.items.types.show', $type));

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $type->id,
                    'name' => $type->name,
                ]
            ]);
    });

    it('can update a type', function () {
        $type = ItemType::factory()->create();
        $data = [
            'name' => 'Updated Type',
            'description' => 'Updated Description',
        ];

        $response = $this->putJson(route('setups.items.types.update', $type), $data);

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'name' => 'Updated Type',
                    'description' => 'Updated Description',
                ]
            ]);

        $this->assertDatabaseHas('item_types', [
            'id' => $type->id,
            'name' => 'Updated Type',
        ]);
    });

    it('can delete a type', function () {
        $type = ItemType::factory()->create();

        $response = $this->deleteJson(route('setups.items.types.destroy', $type));

        $response->assertNoContent();
        $this->assertSoftDeleted('item_types', ['id' => $type->id]);
    });

    it('validates required fields when creating', function () {
        $response = $this->postJson(route('setups.items.types.store'), []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('validates unique name when creating', function () {
        $existingType = ItemType::factory()->create();

        $response = $this->postJson(route('setups.items.types.store'), [
            'name' => $existingType->name,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('validates unique name when updating', function () {
        $type1 = ItemType::factory()->create();
        $type2 = ItemType::factory()->create();

        $response = $this->putJson(route('setups.items.types.update', $type1), [
            'name' => $type2->name,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('can search types', function () {
        ItemType::factory()->create(['name' => 'Searchable Type']);
        ItemType::factory()->create(['name' => 'Another Type']);

        $response = $this->getJson(route('setups.items.types.index', ['search' => 'Searchable']));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['name'])->toBe('Searchable Type');
    });

    it('can filter by active status', function () {
        ItemType::factory()->active()->create();
        ItemType::factory()->inactive()->create();

        $response = $this->getJson(route('setups.items.types.index', ['is_active' => true]));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['is_active'])->toBe(true);
    });

    it('can sort types', function () {
        ItemType::factory()->create(['name' => 'B Type']);
        ItemType::factory()->create(['name' => 'A Type']);

        $response = $this->getJson(route('setups.items.types.index', [
            'sort_by' => 'name',
            'sort_direction' => 'asc'
        ]));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data[0]['name'])->toBe('A Type');
        expect($data[1]['name'])->toBe('B Type');
    });

    it('returns 404 for non-existent type', function () {
        $response = $this->getJson(route('setups.items.types.show', 999));

        $response->assertNotFound();
    });

    it('can list trashed types', function () {
        $type = ItemType::factory()->create();
        $type->delete();

        $response = $this->getJson(route('setups.items.types.trashed'));

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
        $type = ItemType::factory()->create();
        $type->delete();

        $response = $this->patchJson(route('setups.items.types.restore', $type->id));

        $response->assertOk();
        $this->assertDatabaseHas('item_types', [
            'id' => $type->id,
            'deleted_at' => null,
        ]);
    });

    it('can force delete a trashed type', function () {
        $type = ItemType::factory()->create();
        $type->delete();

        $response = $this->deleteJson(route('setups.items.types.force-delete', $type->id));

        $response->assertNoContent();
        $this->assertDatabaseMissing('item_types', ['id' => $type->id]);
    });

    it('returns 404 when trying to restore non-existent trashed type', function () {
        $response = $this->patchJson(route('setups.items.types.restore', 999));

        $response->assertNotFound();
    });

    it('returns 404 when trying to force delete non-existent trashed type', function () {
        $response = $this->deleteJson(route('setups.items.types.force-delete', 999));

        $response->assertNotFound();
    });
});