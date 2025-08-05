<?php

use App\Models\Setups\ItemBrand;
use App\Models\User;

uses()->group('api', 'setup', 'setup.items.brands', 'item_brands');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'sanctum');
});

describe('Brands API', function () {
    it('can list brands', function () {
        ItemBrand::factory()->count(3)->create();

        $response = $this->getJson(route('setups.items.brands.index'));

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

        $response = $this->postJson(route('setups.items.brands.store'), $data);

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

        $this->assertDatabaseHas('item_brands', [
            'name' => 'Test Brand',
            'description' => 'Test Description',
        ]);
    });

    it('can show a brand', function () {
        $brand = ItemBrand::factory()->create();

        $response = $this->getJson(route('setups.items.brands.show', $brand));

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $brand->id,
                    'name' => $brand->name,
                ]
            ]);
    });

    it('can update a brand', function () {
        $brand = ItemBrand::factory()->create();
        $data = [
            'name' => 'Updated Brand',
            'description' => 'Updated Description',
        ];

        $response = $this->putJson(route('setups.items.brands.update', $brand), $data);

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'name' => 'Updated Brand',
                    'description' => 'Updated Description',
                ]
            ]);

        $this->assertDatabaseHas('item_brands', [
            'id' => $brand->id,
            'name' => 'Updated Brand',
        ]);
    });

    it('can delete a brand', function () {
        $brand = ItemBrand::factory()->create();

        $response = $this->deleteJson(route('setups.items.brands.destroy', $brand));

        $response->assertNoContent();
        $this->assertSoftDeleted('item_brands', ['id' => $brand->id]);
    });

    it('validates required fields when creating', function () {
        $response = $this->postJson(route('setups.items.brands.store'), []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('validates unique name when creating', function () {
        $existingBrand = ItemBrand::factory()->create();

        $response = $this->postJson(route('setups.items.brands.store'), [
            'name' => $existingBrand->name,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('validates unique name when updating', function () {
        $brand1 = ItemBrand::factory()->create();
        $brand2 = ItemBrand::factory()->create();

        $response = $this->putJson(route('setups.items.brands.update', $brand1), [
            'name' => $brand2->name,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('can search brands', function () {
        ItemBrand::factory()->create(['name' => 'Searchable Brand']);
        ItemBrand::factory()->create(['name' => 'Another Brand']);

        $response = $this->getJson(route('setups.items.brands.index', ['search' => 'Searchable']));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['name'])->toBe('Searchable Brand');
    });

    it('can filter by active status', function () {
        ItemBrand::factory()->active()->create();
        ItemBrand::factory()->inactive()->create();

        $response = $this->getJson(route('setups.items.brands.index', ['is_active' => true]));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['is_active'])->toBe(true);
    });

    it('can sort brands', function () {
        ItemBrand::factory()->create(['name' => 'B Brand']);
        ItemBrand::factory()->create(['name' => 'A Brand']);

        $response = $this->getJson(route('setups.items.brands.index', [
            'sort_by' => 'name',
            'sort_direction' => 'asc'
        ]));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data[0]['name'])->toBe('A Brand');
        expect($data[1]['name'])->toBe('B Brand');
    });

    it('returns 404 for non-existent brand', function () {
        $response = $this->getJson(route('setups.items.brands.show', 999));

        $response->assertNotFound();
    });

    it('can list trashed brands', function () {
        $brand = ItemBrand::factory()->create();
        $brand->delete();

        $response = $this->getJson(route('setups.items.brands.trashed'));

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
        $brand = ItemBrand::factory()->create();
        $brand->delete();

        $response = $this->patchJson(route('setups.items.brands.restore', $brand->id));

        $response->assertOk();
        $this->assertDatabaseHas('item_brands', [
            'id' => $brand->id,
            'deleted_at' => null,
        ]);
    });

    it('can force delete a trashed brand', function () {
        $brand = ItemBrand::factory()->create();
        $brand->delete();

        $response = $this->deleteJson(route('setups.items.brands.force-delete', $brand->id));

        $response->assertNoContent();
        $this->assertDatabaseMissing('item_brands', ['id' => $brand->id]);
    });

    it('returns 404 when trying to restore non-existent trashed brand', function () {
        $response = $this->patchJson(route('setups.items.brands.restore', 999));

        $response->assertNotFound();
    });

    it('returns 404 when trying to force delete non-existent trashed brand', function () {
        $response = $this->deleteJson(route('setups.items.brands.force-delete', 999));

        $response->assertNotFound();
    });
});