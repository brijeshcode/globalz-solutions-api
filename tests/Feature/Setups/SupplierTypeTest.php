<?php

use App\Models\Setups\SupplierType;
use App\Models\User;

uses()->group('api', 'setup', 'setup.types', 'supplier_types');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'sanctum');
});

describe('Supplier Types API', function () {
    it('can list supplier types', function () {
        SupplierType::factory()->count(3)->create();

        $response = $this->getJson(route('setups.supplier-types.index'));

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

    it('can create a supplier type', function () {
        $data = [
            'name' => 'Test Supplier Type',
            'description' => 'Test Description',
            'is_active' => true,
        ];

        $response = $this->postJson(route('setups.supplier-types.store'), $data);

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

        $this->assertDatabaseHas('supplier_types', [
            'name' => 'Test Supplier Type',
            'description' => 'Test Description',
        ]);
    });

    it('can show a supplier type', function () {
        $type = SupplierType::factory()->create();

        $response = $this->getJson(route('setups.supplier-types.show', $type));

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $type->id,
                    'name' => $type->name,
                ]
            ]);
    });

    it('can update a supplier type', function () {
        $type = SupplierType::factory()->create();
        $data = [
            'name' => 'Updated Supplier Type',
            'description' => 'Updated Description',
        ];

        $response = $this->putJson(route('setups.supplier-types.update', $type), $data);

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'name' => 'Updated Supplier Type',
                    'description' => 'Updated Description',
                ]
            ]);

        $this->assertDatabaseHas('supplier_types', [
            'id' => $type->id,
            'name' => 'Updated Supplier Type',
        ]);
    });

    it('can delete a supplier type', function () {
        $type = SupplierType::factory()->create();

        $response = $this->deleteJson(route('setups.supplier-types.destroy', $type));

        $response->assertNoContent();
        $this->assertSoftDeleted('supplier_types', ['id' => $type->id]);
    });

    it('validates required fields when creating', function () {
        $response = $this->postJson(route('setups.supplier-types.store'), []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('validates unique name when creating', function () {
        $existingType = SupplierType::factory()->create();

        $response = $this->postJson(route('setups.supplier-types.store'), [
            'name' => $existingType->name,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('validates unique name when updating', function () {
        $type1 = SupplierType::factory()->create();
        $type2 = SupplierType::factory()->create();

        $response = $this->putJson(route('setups.supplier-types.update', $type1), [
            'name' => $type2->name,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('allows updating supplier type with its own name', function () {
        $type = SupplierType::factory()->create(['name' => 'Test Supplier Type']);

        $response = $this->putJson(route('setups.supplier-types.update', $type), [
            'name' => 'Test Supplier Type', // Same name should be allowed
            'description' => 'Updated description',
        ]);

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'name' => 'Test Supplier Type',
                    'description' => 'Updated description',
                ]
            ]);
    });

    it('can search supplier types', function () {
        SupplierType::factory()->create(['name' => 'Searchable Supplier Type']);
        SupplierType::factory()->create(['name' => 'Another Supplier Type']);

        $response = $this->getJson(route('setups.supplier-types.index', ['search' => 'Searchable']));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['name'])->toBe('Searchable Supplier Type');
    });

    it('can filter by active status', function () {
        SupplierType::factory()->active()->create();
        SupplierType::factory()->inactive()->create();

        $response = $this->getJson(route('setups.supplier-types.index', ['is_active' => true]));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['is_active'])->toBe(true);
    });

    it('can sort supplier types', function () {
        SupplierType::factory()->create(['name' => 'B Supplier Type']);
        SupplierType::factory()->create(['name' => 'A Supplier Type']);

        $response = $this->getJson(route('setups.supplier-types.index', [
            'sort_by' => 'name',
            'sort_direction' => 'asc'
        ]));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data[0]['name'])->toBe('A Supplier Type');
        expect($data[1]['name'])->toBe('B Supplier Type');
    });

    it('returns 404 for non-existent supplier type', function () {
        $response = $this->getJson(route('setups.supplier-types.show', 999));

        $response->assertNotFound();
    });

    it('can list trashed supplier types', function () {
        $type = SupplierType::factory()->create();
        $type->delete();

        $response = $this->getJson(route('setups.supplier-types.trashed'));

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

    it('can restore a trashed supplier type', function () {
        $type = SupplierType::factory()->create();
        $type->delete();

        $response = $this->patchJson(route('setups.supplier-types.restore', $type->id));

        $response->assertOk();
        $this->assertDatabaseHas('supplier_types', [
            'id' => $type->id,
            'deleted_at' => null,
        ]);
    });

    it('can force delete a trashed supplier type', function () {
        $type = SupplierType::factory()->create();
        $type->delete();

        $response = $this->deleteJson(route('setups.supplier-types.force-delete', $type->id));

        $response->assertNoContent();
        $this->assertDatabaseMissing('supplier_types', ['id' => $type->id]);
    });

    it('returns 404 when trying to restore non-existent trashed supplier type', function () {
        $response = $this->patchJson(route('setups.supplier-types.restore', 999));

        $response->assertNotFound();
    });

    it('returns 404 when trying to force delete non-existent trashed supplier type', function () {
        $response = $this->deleteJson(route('setups.supplier-types.force-delete', 999));

        $response->assertNotFound();
    });

    it('can search in description field', function () {
        SupplierType::factory()->create([
            'name' => 'Type One',
            'description' => 'Special manufacturer description'
        ]);
        SupplierType::factory()->create([
            'name' => 'Type Two', 
            'description' => 'Regular distributor description'
        ]);

        $response = $this->getJson(route('setups.supplier-types.index', ['search' => 'manufacturer']));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['description'])->toContain('manufacturer');
    });

    it('sets created_by when creating supplier type', function () {
        $data = [
            'name' => 'Test Supplier Type',
            'description' => 'Test Description',
            'is_active' => true,
        ];

        $response = $this->postJson(route('setups.supplier-types.store'), $data);

        $response->assertCreated();
        
        $this->assertDatabaseHas('supplier_types', [
            'name' => 'Test Supplier Type',
            'created_by' => $this->user->id,
        ]);
    });

    it('sets updated_by when updating supplier type', function () {
        $type = SupplierType::factory()->create();
        $data = [
            'name' => 'Updated Supplier Type',
            'description' => 'Updated Description',
        ];

        $response = $this->putJson(route('setups.supplier-types.update', $type), $data);

        $response->assertOk();
        
        $this->assertDatabaseHas('supplier_types', [
            'id' => $type->id,
            'name' => 'Updated Supplier Type',
            'updated_by' => $this->user->id,
        ]);
    });
});