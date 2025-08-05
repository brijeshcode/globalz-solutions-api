<?php

use App\Models\Category;
use App\Models\User;

uses()->group('api', 'setup', 'setup.categories', 'categories');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'sanctum');
});

describe('Categories API', function () {
    it('can list categories', function () {
        Category::factory()->count(3)->create();

        $response = $this->getJson(route('setups.categories.index'));

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

        $response = $this->postJson(route('setups.categories.store'), $data);

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

        $this->assertDatabaseHas('categories', [
            'name' => 'Test Category',
            'description' => 'Test Description',
        ]);
    });

    it('can show a category', function () {
        $category = Category::factory()->create();

        $response = $this->getJson(route('setups.categories.show', $category));

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $category->id,
                    'name' => $category->name,
                ]
            ]);
    });

    it('can update a category', function () {
        $category = Category::factory()->create();
        $data = [
            'name' => 'Updated Category',
            'description' => 'Updated Description',
        ];

        $response = $this->putJson(route('setups.categories.update', $category), $data);

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'name' => 'Updated Category',
                    'description' => 'Updated Description',
                ]
            ]);

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'name' => 'Updated Category',
        ]);
    });

    it('can delete a category', function () {
        $category = Category::factory()->create();

        $response = $this->deleteJson(route('setups.categories.destroy', $category));

        $response->assertNoContent();
        $this->assertSoftDeleted('categories', ['id' => $category->id]);
    });

    it('validates required fields when creating', function () {
        $response = $this->postJson(route('setups.categories.store'), []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('validates unique name when creating', function () {
        $existingCategory = Category::factory()->create();

        $response = $this->postJson(route('setups.categories.store'), [
            'name' => $existingCategory->name,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('validates unique name when updating', function () {
        $category1 = Category::factory()->create();
        $category2 = Category::factory()->create();

        $response = $this->putJson(route('setups.categories.update', $category1), [
            'name' => $category2->name,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('can search categories', function () {
        Category::factory()->create(['name' => 'Searchable Category']);
        Category::factory()->create(['name' => 'Another Category']);

        $response = $this->getJson(route('setups.categories.index', ['search' => 'Searchable']));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['name'])->toBe('Searchable Category');
    });

    it('can filter by active status', function () {
        Category::factory()->active()->create();
        Category::factory()->inactive()->create();

        $response = $this->getJson(route('setups.categories.index', ['is_active' => true]));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['is_active'])->toBe(true);
    });

    it('can sort categories', function () {
        Category::factory()->create(['name' => 'B Category']);
        Category::factory()->create(['name' => 'A Category']);

        $response = $this->getJson(route('setups.categories.index', [
            'sort_by' => 'name',
            'sort_direction' => 'asc'
        ]));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data[0]['name'])->toBe('A Category');
        expect($data[1]['name'])->toBe('B Category');
    });

    it('returns 404 for non-existent category', function () {
        $response = $this->getJson(route('setups.categories.show', 999));

        $response->assertNotFound();
    });

    it('can list trashed categories', function () {
        $category = Category::factory()->create();
        $category->delete();

        $response = $this->getJson(route('setups.categories.trashed'));

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
        $category = Category::factory()->create();
        $category->delete();

        $response = $this->patchJson(route('setups.categories.restore', $category->id));

        $response->assertOk();
        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'deleted_at' => null,
        ]);
    });

    it('can force delete a trashed category', function () {
        $category = Category::factory()->create();
        $category->delete();

        $response = $this->deleteJson(route('setups.categories.force-delete', $category->id));

        $response->assertNoContent();
        $this->assertDatabaseMissing('categories', ['id' => $category->id]);
    });

    it('returns 404 when trying to restore non-existent trashed category', function () {
        $response = $this->patchJson(route('setups.categories.restore', 999));

        $response->assertNotFound();
    });

    it('returns 404 when trying to force delete non-existent trashed category', function () {
        $response = $this->deleteJson(route('setups.categories.force-delete', 999));

        $response->assertNotFound();
    });
});