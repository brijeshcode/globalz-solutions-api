<?php

use App\Models\Setups\Warehouse;
use App\Models\User;

uses()->group('api', 'setup', 'setup.warehouses', 'warehouses');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'sanctum');
});

describe('Warehouses API', function () {
    it('can list warehouses', function () {
        Warehouse::factory()->count(3)->create();

        $response = $this->getJson(route('setups.warehouses.index'));

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'note',
                        'is_active',
                        'address_line_1',
                        'address_line_2',
                        'city',
                        'state',
                        'postal_code',
                        'country',
                        'full_address',
                        'created_by',
                        'updated_by',
                        'created_at',
                        'updated_at',
                    ]
                ],
                'pagination'
            ]);
    });

    it('can create a warehouse', function () {
        $data = [
            'name' => 'Test Warehouse',
            'note' => 'Test Note',
            'is_active' => true,
            'address_line_1' => '123 Storage St',
            'address_line_2' => 'Suite 100',
            'city' => 'New York',
            'state' => 'NY',
            'postal_code' => '10001',
            'country' => 'United States',
        ];

        $response = $this->postJson(route('setups.warehouses.store'), $data);

        $response->assertCreated()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'name',
                    'note',
                    'is_active',
                    'address_line_1',
                    'address_line_2',
                    'city',
                    'state',
                    'postal_code',
                    'country',
                    'full_address',
                ]
            ]);

        $this->assertDatabaseHas('warehouses', [
            'name' => 'Test Warehouse',
            'city' => 'New York',
            'state' => 'NY',
        ]);
    });

    it('can show a warehouse', function () {
        $warehouse = Warehouse::factory()->create();

        $response = $this->getJson(route('setups.warehouses.show', $warehouse));

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $warehouse->id,
                    'name' => $warehouse->name,
                ]
            ]);
    });

    it('can update a warehouse', function () {
        $warehouse = Warehouse::factory()->create();
        $data = [
            'name' => 'Updated Warehouse',
            'note' => 'Updated Note',
            'address_line_1' => 'Updated Address',
            'city' => 'Updated City',
            'state' => 'Updated State',
            'postal_code' => 'Updated Code',
            'country' => 'Updated Country',
        ];

        $response = $this->putJson(route('setups.warehouses.update', $warehouse), $data);

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'name' => 'Updated Warehouse',
                    'note' => 'Updated Note',
                    'city' => 'Updated City',
                ]
            ]);

        $this->assertDatabaseHas('warehouses', [
            'id' => $warehouse->id,
            'name' => 'Updated Warehouse',
            'city' => 'Updated City',
        ]);
    });

    it('can delete a warehouse', function () {
        $warehouse = Warehouse::factory()->create();

        $response = $this->deleteJson(route('setups.warehouses.destroy', $warehouse));

        $response->assertNoContent();
        $this->assertSoftDeleted('warehouses', ['id' => $warehouse->id]);
    });

    it('validates required fields when creating', function () {
        $response = $this->postJson(route('setups.warehouses.store'), []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'name',
                // 'address_line_1',
                // 'city',
                // 'state',
                // 'postal_code',
                // 'country',
            ]);
    });

    it('validates unique name when creating', function () {
        $existingWarehouse = Warehouse::factory()->create();

        $response = $this->postJson(route('setups.warehouses.store'), [
            'name' => $existingWarehouse->name,
            'address_line_1' => '123 Test St',
            'city' => 'Test City',
            'state' => 'Test State',
            'postal_code' => '12345',
            'country' => 'Test Country',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('validates unique name when updating', function () {
        $warehouse1 = Warehouse::factory()->create();
        $warehouse2 = Warehouse::factory()->create();

        $response = $this->putJson(route('setups.warehouses.update', $warehouse1), [
            'name' => $warehouse2->name,
            'address_line_1' => '123 Test St',
            'city' => 'Test City',
            'state' => 'Test State',
            'postal_code' => '12345',
            'country' => 'Test Country',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('allows updating warehouse with its own name', function () {
        $warehouse = Warehouse::factory()->create(['name' => 'Test Warehouse']);

        $response = $this->putJson(route('setups.warehouses.update', $warehouse), [
            'name' => 'Test Warehouse', // Same name should be allowed
            'note' => 'Updated note',
            'address_line_1' => '123 Test St',
            'city' => 'Test City',
            'state' => 'Test State',
            'postal_code' => '12345',
            'country' => 'Test Country',
        ]);

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'name' => 'Test Warehouse',
                    'note' => 'Updated note',
                ]
            ]);
    });

    it('can search warehouses', function () {
        Warehouse::factory()->create(['name' => 'Searchable Warehouse']);
        Warehouse::factory()->create(['name' => 'Another Warehouse']);

        $response = $this->getJson(route('setups.warehouses.index', ['search' => 'Searchable']));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['name'])->toBe('Searchable Warehouse');
    });

    it('can filter by active status', function () {
        Warehouse::factory()->active()->create();
        Warehouse::factory()->inactive()->create();

        $response = $this->getJson(route('setups.warehouses.index', ['is_active' => true]));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['is_active'])->toBe(true);
    });

    it('can filter by city', function () {
        Warehouse::factory()->create(['city' => 'New York']);
        Warehouse::factory()->create(['city' => 'Los Angeles']);

        $response = $this->getJson(route('setups.warehouses.index', ['city' => 'New']));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['city'])->toBe('New York');
    });

    it('can filter by state', function () {
        Warehouse::factory()->create(['state' => 'California']);
        Warehouse::factory()->create(['state' => 'Texas']);

        $response = $this->getJson(route('setups.warehouses.index', ['state' => 'Cal']));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['state'])->toBe('California');
    });

    it('can filter by country', function () {
        Warehouse::factory()->create(['country' => 'United States']);
        Warehouse::factory()->create(['country' => 'Canada']);

        $response = $this->getJson(route('setups.warehouses.index', ['country' => 'United']));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['country'])->toBe('United States');
    });

    it('can sort warehouses', function () {
        Warehouse::factory()->create(['name' => 'B Warehouse']);
        Warehouse::factory()->create(['name' => 'A Warehouse']);

        $response = $this->getJson(route('setups.warehouses.index', [
            'sort_by' => 'name',
            'sort_direction' => 'asc'
        ]));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data[0]['name'])->toBe('A Warehouse');
        expect($data[1]['name'])->toBe('B Warehouse');
    });

    it('returns 404 for non-existent warehouse', function () {
        $response = $this->getJson(route('setups.warehouses.show', 999));

        $response->assertNotFound();
    });

    it('can list trashed warehouses', function () {
        $warehouse = Warehouse::factory()->create();
        $warehouse->delete();

        $response = $this->getJson(route('setups.warehouses.trashed'));

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'note',
                        'is_active',
                        'address_line_1',
                        'address_line_2',
                        'city',
                        'state',
                        'postal_code',
                        'country',
                        'full_address',
                    ]
                ],
                'pagination'
            ]);

        expect($response->json('data'))->toHaveCount(1);
    });

    it('can restore a trashed warehouse', function () {
        $warehouse = Warehouse::factory()->create();
        $warehouse->delete();

        $response = $this->patchJson(route('setups.warehouses.restore', $warehouse->id));

        $response->assertOk();
        $this->assertDatabaseHas('warehouses', [
            'id' => $warehouse->id,
            'deleted_at' => null,
        ]);
    });

    it('can force delete a trashed warehouse', function () {
        $warehouse = Warehouse::factory()->create();
        $warehouse->delete();

        $response = $this->deleteJson(route('setups.warehouses.force-delete', $warehouse->id));

        $response->assertNoContent();
        $this->assertDatabaseMissing('warehouses', ['id' => $warehouse->id]);
    });

    it('returns 404 when trying to restore non-existent trashed warehouse', function () {
        $response = $this->patchJson(route('setups.warehouses.restore', 999));

        $response->assertNotFound();
    });

    it('returns 404 when trying to force delete non-existent trashed warehouse', function () {
        $response = $this->deleteJson(route('setups.warehouses.force-delete', 999));

        $response->assertNotFound();
    });

    it('can create new warehouse name and is_active filed only', function () {
        $data = [
            'name' => 'Minimal Warehouse',
            'is_active' => true,
        ];

        $response = $this->postJson(route('setups.warehouses.store'), $data);

        $response->assertCreated()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'name',
                    'is_active',
                ]
            ])
            ->assertJson([
                'data' => [
                    'name' => 'Minimal Warehouse',
                    'is_active' => true,
                ]
            ]);

        $this->assertDatabaseHas('warehouses', [
            'name' => 'Minimal Warehouse',
            'is_active' => true,
        ]);
    });
    it('can update warehouse name and is_active filed only', function () {
        $warehouse = Warehouse::factory()->create([
            'name' => 'Original Warehouse',
            'is_active' => false,
        ]);

        $data = [
            'name' => 'Updated Minimal Warehouse',
            'is_active' => true,
        ];

        $response = $this->putJson(route('setups.warehouses.update', $warehouse), $data);

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $warehouse->id,
                    'name' => 'Updated Minimal Warehouse',
                    'is_active' => true,
                ]
            ]);

        $this->assertDatabaseHas('warehouses', [
            'id' => $warehouse->id,
            'name' => 'Updated Minimal Warehouse',
            'is_active' => true,
        ]);
    });

    it('can set a warehouse as default', function () {
        $warehouse1 = Warehouse::factory()->create(['is_default' => true]);
        $warehouse2 = Warehouse::factory()->create(['is_default' => false]);

        $response = $this->patchJson(route('setups.warehouses.setDefault', $warehouse2->id));

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $warehouse2->id,
                    'is_default' => true,
                ]
            ]);

        $this->assertDatabaseHas('warehouses', [
            'id' => $warehouse1->id,
            'is_default' => false,
        ]);

        $this->assertDatabaseHas('warehouses', [
            'id' => $warehouse2->id,
            'is_default' => true,
        ]);
    });

    it('cannot set warehouse as default with invalid warehouse id', function () {
        $response = $this->patchJson(route('setups.warehouses.setDefault', 999));

        $response->assertNotFound();
    });
});