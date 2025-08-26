<?php

use App\Models\Setups\Customers\CustomerType;
use App\Models\User;

uses()->group('api', 'setup', 'setup.types', 'customer_types');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'sanctum');
});

describe('Customer Types API', function () {
    it('can list customer types', function () {
        CustomerType::factory()->count(3)->create();

        $response = $this->getJson(route('setups.customer.types.index'));

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

    it('can create a customer type', function () {
        $data = [
            'name' => 'Test Customer Type',
            'description' => 'Test Description',
            'is_active' => true,
        ];

        $response = $this->postJson(route('setups.customer.types.store'), $data);

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

        $this->assertDatabaseHas('customer_types', [
            'name' => 'Test Customer Type',
            'description' => 'Test Description',
        ]);
    });

    it('can show a customer type', function () {
        $type = CustomerType::factory()->create();

        $response = $this->getJson(route('setups.customer.types.show', $type));

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $type->id,
                    'name' => $type->name,
                ]
            ]);
    });

    it('can update a customer type', function () {
        $type = CustomerType::factory()->create();
        $data = [
            'name' => 'Updated Customer Type',
            'description' => 'Updated Description',
        ];

        $response = $this->putJson(route('setups.customer.types.update', $type), $data);

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'name' => 'Updated Customer Type',
                    'description' => 'Updated Description',
                ]
            ]);

        $this->assertDatabaseHas('customer_types', [
            'id' => $type->id,
            'name' => 'Updated Customer Type',
        ]);
    });

    it('can delete a customer type', function () {
        $type = CustomerType::factory()->create();

        $response = $this->deleteJson(route('setups.customer.types.destroy', $type));

        $response->assertNoContent();
        $this->assertSoftDeleted('customer_types', ['id' => $type->id]);
    });

    it('validates required fields when creating', function () {
        $response = $this->postJson(route('setups.customer.types.store'), []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('validates unique name when creating', function () {
        $existingType = CustomerType::factory()->create();

        $response = $this->postJson(route('setups.customer.types.store'), [
            'name' => $existingType->name,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('validates unique name when updating', function () {
        $type1 = CustomerType::factory()->create();
        $type2 = CustomerType::factory()->create();

        $response = $this->putJson(route('setups.customer.types.update', $type1), [
            'name' => $type2->name,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('allows updating customer type with its own name', function () {
        $type = CustomerType::factory()->create(['name' => 'Test Customer Type']);

        $response = $this->putJson(route('setups.customer.types.update', $type), [
            'name' => 'Test Customer Type', // Same name should be allowed
            'description' => 'Updated description',
        ]);

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'name' => 'Test Customer Type',
                    'description' => 'Updated description',
                ]
            ]);
    });

    it('can search customer types', function () {
        CustomerType::factory()->create(['name' => 'Searchable Customer Type']);
        CustomerType::factory()->create(['name' => 'Another Customer Type']);

        $response = $this->getJson(route('setups.customer.types.index', ['search' => 'Searchable']));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['name'])->toBe('Searchable Customer Type');
    });

    it('can filter by active status', function () {
        CustomerType::factory()->active()->create();
        CustomerType::factory()->inactive()->create();

        $response = $this->getJson(route('setups.customer.types.index', ['is_active' => true]));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['is_active'])->toBe(true);
    });

    it('can sort customer types', function () {
        CustomerType::factory()->create(['name' => 'B Customer Type']);
        CustomerType::factory()->create(['name' => 'A Customer Type']);

        $response = $this->getJson(route('setups.customer.types.index', [
            'sort_by' => 'name',
            'sort_direction' => 'asc'
        ]));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data[0]['name'])->toBe('A Customer Type');
        expect($data[1]['name'])->toBe('B Customer Type');
    });

    it('returns 404 for non-existent customer type', function () {
        $response = $this->getJson(route('setups.customer.types.show', 999));

        $response->assertNotFound();
    });

    it('can list trashed customer types', function () {
        $type = CustomerType::factory()->create();
        $type->delete();

        $response = $this->getJson(route('setups.customer.types.trashed'));

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

    it('can restore a trashed customer type', function () {
        $type = CustomerType::factory()->create();
        $type->delete();

        $response = $this->patchJson(route('setups.customer.types.restore', $type->id));

        $response->assertOk();
        $this->assertDatabaseHas('customer_types', [
            'id' => $type->id,
            'deleted_at' => null,
        ]);
    });

    it('can force delete a trashed customer type', function () {
        $type = CustomerType::factory()->create();
        $type->delete();

        $response = $this->deleteJson(route('setups.customer.types.force-delete', $type->id));

        $response->assertNoContent();
        $this->assertDatabaseMissing('customer_types', ['id' => $type->id]);
    });

    it('returns 404 when trying to restore non-existent trashed customer type', function () {
        $response = $this->patchJson(route('setups.customer.types.restore', 999));

        $response->assertNotFound();
    });

    it('returns 404 when trying to force delete non-existent trashed customer type', function () {
        $response = $this->deleteJson(route('setups.customer.types.force-delete', 999));

        $response->assertNotFound();
    });

    it('can search in description field', function () {
        CustomerType::factory()->create([
            'name' => 'Type One',
            'description' => 'Special retail description'
        ]);
        CustomerType::factory()->create([
            'name' => 'Type Two', 
            'description' => 'Regular wholesale description'
        ]);

        $response = $this->getJson(route('setups.customer.types.index', ['search' => 'retail']));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['description'])->toContain('retail');
    });

    it('sets created_by when creating customer type', function () {
        $data = [
            'name' => 'Test Customer Type',
            'description' => 'Test Description',
            'is_active' => true,
        ];

        $response = $this->postJson(route('setups.customer.types.store'), $data);

        $response->assertCreated();
        
        $this->assertDatabaseHas('customer_types', [
            'name' => 'Test Customer Type',
            'created_by' => $this->user->id,
        ]);
    });

    it('sets updated_by when updating customer type', function () {
        $type = CustomerType::factory()->create();
        $data = [
            'name' => 'Updated Customer Type',
            'description' => 'Updated Description',
        ];

        $response = $this->putJson(route('setups.customer.types.update', $type), $data);

        $response->assertOk();
        
        $this->assertDatabaseHas('customer_types', [
            'id' => $type->id,
            'name' => 'Updated Customer Type',
            'updated_by' => $this->user->id,
        ]);
    });
});
