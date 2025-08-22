<?php

use App\Models\Setups\ItemCategory;
use App\Models\User;

uses()->group('api', 'setup', 'setup.items.categories', 'item_categories');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'sanctum');
});

describe('Categories API', function () {
    it('can list categories', function () {
        ItemCategory::factory()->count(3)->create();

        $response = $this->getJson(route('setups.items.categories.index'));

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

    it('can create a category', function () {
        $data = [
            'name' => 'Test Category',
            'description' => 'Test Description',
            'is_active' => true,
        ];

        $response = $this->postJson(route('setups.items.categories.store'), $data);

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

        $this->assertDatabaseHas('item_categories', [
            'name' => 'Test Category',
            'description' => 'Test Description',
        ]);
    });

    it('can show a category', function () {
        $category = ItemCategory::factory()->create();

        $response = $this->getJson(route('setups.items.categories.show', $category));

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $category->id,
                    'name' => $category->name,
                ]
            ]);
    });

    it('can update a category', function () {
        $category = ItemCategory::factory()->create();
        $data = [
            'name' => 'Updated Category',
            'description' => 'Updated Description',
        ];

        $response = $this->putJson(route('setups.items.categories.update', $category), $data);

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'name' => 'Updated Category',
                    'description' => 'Updated Description',
                ]
            ]);

        $this->assertDatabaseHas('item_categories', [
            'id' => $category->id,
            'name' => 'Updated Category',
        ]);
    });

    it('can delete a category', function () {
        $category = ItemCategory::factory()->create();

        $response = $this->deleteJson(route('setups.items.categories.destroy', $category));

        $response->assertNoContent();
        $this->assertSoftDeleted('item_categories', ['id' => $category->id]);
    });

    it('validates required fields when creating', function () {
        $response = $this->postJson(route('setups.items.categories.store'), []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('validates unique name when creating', function () {
        $existingCategory = ItemCategory::factory()->create();

        $response = $this->postJson(route('setups.items.categories.store'), [
            'name' => $existingCategory->name,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('validates unique name when updating', function () {
        $category1 = ItemCategory::factory()->create();
        $category2 = ItemCategory::factory()->create();

        $response = $this->putJson(route('setups.items.categories.update', $category1), [
            'name' => $category2->name,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('allows updating category with its own name', function () {
        $category = ItemCategory::factory()->create(['name' => 'Test Category']);

        $response = $this->putJson(route('setups.items.categories.update', $category), [
            'name' => 'Test Category', // Same name should be allowed
            'description' => 'Updated description',
        ]);

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'name' => 'Test Category',
                    'description' => 'Updated description',
                ]
            ]);
    });

    it('can search categories', function () {
        ItemCategory::factory()->create(['name' => 'Searchable Category']);
        ItemCategory::factory()->create(['name' => 'Another Category']);

        $response = $this->getJson(route('setups.items.categories.index', ['search' => 'Searchable']));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['name'])->toBe('Searchable Category');
    });

    it('can filter by active status', function () {
        ItemCategory::factory()->active()->create();
        ItemCategory::factory()->inactive()->create();

        $response = $this->getJson(route('setups.items.categories.index', ['is_active' => true]));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['is_active'])->toBe(true);
    });

    it('can sort categories', function () {
        ItemCategory::factory()->create(['name' => 'B Category']);
        ItemCategory::factory()->create(['name' => 'A Category']);

        $response = $this->getJson(route('setups.items.categories.index', [
            'sort_by' => 'name',
            'sort_direction' => 'asc'
        ]));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data[0]['name'])->toBe('A Category');
        expect($data[1]['name'])->toBe('B Category');
    });

    it('returns 404 for non-existent category', function () {
        $response = $this->getJson(route('setups.items.categories.show', 999));

        $response->assertNotFound();
    });

    it('can list trashed categories', function () {
        $category = ItemCategory::factory()->create();
        $category->delete();

        $response = $this->getJson(route('setups.items.categories.trashed'));

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

    it('can restore a trashed category', function () {
        $category = ItemCategory::factory()->create();
        $category->delete();

        $response = $this->patchJson(route('setups.items.categories.restore', $category->id));

        $response->assertOk();
        $this->assertDatabaseHas('item_categories', [
            'id' => $category->id,
            'deleted_at' => null,
        ]);
    });

    it('can force delete a trashed category', function () {
        $category = ItemCategory::factory()->create();
        $category->delete();

        $response = $this->deleteJson(route('setups.items.categories.force-delete', $category->id));

        $response->assertNoContent();
        $this->assertDatabaseMissing('item_categories', ['id' => $category->id]);
    });

    it('returns 404 when trying to restore non-existent trashed category', function () {
        $response = $this->patchJson(route('setups.items.categories.restore', 999));

        $response->assertNotFound();
    });

    it('returns 404 when trying to force delete non-existent trashed category', function () {
        $response = $this->deleteJson(route('setups.items.categories.force-delete', 999));

        $response->assertNotFound();
    });
});