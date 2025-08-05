<?php

use App\Models\Brand;
use App\Models\User;

uses()->group('api', 'setup', 'setup.brands', 'brands');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'sanctum');
});

describe('Brands API', function () {
    it('can list brands', function () {
        Brand::factory()->count(3)->create();

        $response = $this->getJson(route('setups.brands.index'));

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

    it('can create a brand', function () {
        $data = [
            'name' => 'Test Brand',
            'description' => 'Test Description',
            'is_active' => true,
        ];

        $response = $this->postJson(route('setups.brands.store'), $data);

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

        $this->assertDatabaseHas('brands', [
            'name' => 'Test Brand',
            'description' => 'Test Description',
        ]);
    });

    it('can show a brand', function () {
        $brand = Brand::factory()->create();

        $response = $this->getJson(route('setups.brands.show', $brand));

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $brand->id,
                    'name' => $brand->name,
                ]
            ]);
    });

    it('can update a brand', function () {
        $brand = Brand::factory()->create();
        $data = [
            'name' => 'Updated Brand',
            'description' => 'Updated Description',
        ];

        $response = $this->putJson(route('setups.brands.update', $brand), $data);

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'name' => 'Updated Brand',
                    'description' => 'Updated Description',
                ]
            ]);

        $this->assertDatabaseHas('brands', [
            'id' => $brand->id,
            'name' => 'Updated Brand',
        ]);
    });

    it('can delete a brand', function () {
        $brand = Brand::factory()->create();

        $response = $this->deleteJson(route('setups.brands.destroy', $brand));

        $response->assertNoContent();
        $this->assertSoftDeleted('brands', ['id' => $brand->id]);
    });

    it('validates required fields when creating', function () {
        $response = $this->postJson(route('setups.brands.store'), []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('validates unique name when creating', function () {
        $existingBrand = Brand::factory()->create();

        $response = $this->postJson(route('setups.brands.store'), [
            'name' => $existingBrand->name,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('validates unique name when updating', function () {
        $brand1 = Brand::factory()->create();
        $brand2 = Brand::factory()->create();

        $response = $this->putJson(route('setups.brands.update', $brand1), [
            'name' => $brand2->name,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('can search brands', function () {
        Brand::factory()->create(['name' => 'Searchable Brand']);
        Brand::factory()->create(['name' => 'Another Brand']);

        $response = $this->getJson(route('setups.brands.index', ['search' => 'Searchable']));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['name'])->toBe('Searchable Brand');
    });

    it('can filter by active status', function () {
        Brand::factory()->active()->create();
        Brand::factory()->inactive()->create();

        $response = $this->getJson(route('setups.brands.index', ['is_active' => true]));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['is_active'])->toBe(true);
    });

    it('can sort brands', function () {
        Brand::factory()->create(['name' => 'B Brand']);
        Brand::factory()->create(['name' => 'A Brand']);

        $response = $this->getJson(route('setups.brands.index', [
            'sort_by' => 'name',
            'sort_direction' => 'asc'
        ]));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data[0]['name'])->toBe('A Brand');
        expect($data[1]['name'])->toBe('B Brand');
    });

    it('returns 404 for non-existent brand', function () {
        $response = $this->getJson(route('setups.brands.show', 999));

        $response->assertNotFound();
    });

    it('can list trashed brands', function () {
        $brand = Brand::factory()->create();
        $brand->delete();

        $response = $this->getJson(route('setups.brands.trashed'));

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

    it('can restore a trashed brand', function () {
        $brand = Brand::factory()->create();
        $brand->delete();

        $response = $this->patchJson(route('setups.brands.restore', $brand->id));

        $response->assertOk();
        $this->assertDatabaseHas('brands', [
            'id' => $brand->id,
            'deleted_at' => null,
        ]);
    });

    it('can force delete a trashed brand', function () {
        $brand = Brand::factory()->create();
        $brand->delete();

        $response = $this->deleteJson(route('setups.brands.force-delete', $brand->id));

        $response->assertNoContent();
        $this->assertDatabaseMissing('brands', ['id' => $brand->id]);
    });

    it('returns 404 when trying to restore non-existent trashed brand', function () {
        $response = $this->patchJson(route('setups.brands.restore', 999));

        $response->assertNotFound();
    });

    it('returns 404 when trying to force delete non-existent trashed brand', function () {
        $response = $this->deleteJson(route('setups.brands.force-delete', 999));

        $response->assertNotFound();
    });
});