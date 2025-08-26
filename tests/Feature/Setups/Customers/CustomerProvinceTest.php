<?php

use App\Models\Setups\Customers\CustomerProvince;
use App\Models\User;

uses()->group('api', 'setup', 'setup.customers', 'setup.customers.provinces', 'customer_provinces');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'sanctum');
});

describe('Customer Provinces API', function () {
    it('can list customer provinces', function () {
        CustomerProvince::factory()->count(3)->create();

        $response = $this->getJson(route('setups.customers.provinces.index'));

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

    it('can create a customer province', function () {
        $data = [
            'name' => 'Test Customer Province',
            'description' => 'Test Description',
            'is_active' => true,
        ];

        $response = $this->postJson(route('setups.customers.provinces.store'), $data);

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

        $this->assertDatabaseHas('customer_provinces', [
            'name' => 'Test Customer Province',
            'description' => 'Test Description',
        ]);
    });

    it('can show a customer province', function () {
        $province = CustomerProvince::factory()->create();

        $response = $this->getJson(route('setups.customers.provinces.show', $province));

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $province->id,
                    'name' => $province->name,
                ]
            ]);
    });

    it('can update a customer province', function () {
        $province = CustomerProvince::factory()->create();
        $data = [
            'name' => 'Updated Customer Province',
            'description' => 'Updated Description',
        ];

        $response = $this->putJson(route('setups.customers.provinces.update', $province), $data);

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'name' => 'Updated Customer Province',
                    'description' => 'Updated Description',
                ]
            ]);

        $this->assertDatabaseHas('customer_provinces', [
            'id' => $province->id,
            'name' => 'Updated Customer Province',
        ]);
    });

    it('can delete a customer province', function () {
        $province = CustomerProvince::factory()->create();

        $response = $this->deleteJson(route('setups.customers.provinces.destroy', $province));

        $response->assertNoContent();
        $this->assertSoftDeleted('customer_provinces', ['id' => $province->id]);
    });

    it('validates required fields when creating', function () {
        $response = $this->postJson(route('setups.customers.provinces.store'), []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('validates unique name when creating', function () {
        $existingProvince = CustomerProvince::factory()->create();

        $response = $this->postJson(route('setups.customers.provinces.store'), [
            'name' => $existingProvince->name,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('validates unique name when updating', function () {
        $province1 = CustomerProvince::factory()->create();
        $province2 = CustomerProvince::factory()->create();

        $response = $this->putJson(route('setups.customers.provinces.update', $province1), [
            'name' => $province2->name,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('allows updating customer province with its own name', function () {
        $province = CustomerProvince::factory()->create(['name' => 'Test Customer Province']);

        $response = $this->putJson(route('setups.customers.provinces.update', $province), [
            'name' => 'Test Customer Province', // Same name should be allowed
            'description' => 'Updated description',
        ]);

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'name' => 'Test Customer Province',
                    'description' => 'Updated description',
                ]
            ]);
    });

    it('can search customer provinces', function () {
        CustomerProvince::factory()->create(['name' => 'Searchable Customer Province']);
        CustomerProvince::factory()->create(['name' => 'Another Customer Province']);

        $response = $this->getJson(route('setups.customers.provinces.index', ['search' => 'Searchable']));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['name'])->toBe('Searchable Customer Province');
    });

    it('can filter by active status', function () {
        CustomerProvince::factory()->active()->create();
        CustomerProvince::factory()->inactive()->create();

        $response = $this->getJson(route('setups.customers.provinces.index', ['is_active' => true]));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['is_active'])->toBe(true);
    });

    it('can sort customer provinces', function () {
        CustomerProvince::factory()->create(['name' => 'B Customer Province']);
        CustomerProvince::factory()->create(['name' => 'A Customer Province']);

        $response = $this->getJson(route('setups.customers.provinces.index', [
            'sort_by' => 'name',
            'sort_direction' => 'asc'
        ]));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data[0]['name'])->toBe('A Customer Province');
        expect($data[1]['name'])->toBe('B Customer Province');
    });

    it('returns 404 for non-existent customer province', function () {
        $response = $this->getJson(route('setups.customers.provinces.show', 999));

        $response->assertNotFound();
    });

    it('can list trashed customer provinces', function () {
        $province = CustomerProvince::factory()->create();
        $province->delete();

        $response = $this->getJson(route('setups.customers.provinces.trashed'));

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

    it('can restore a trashed customer province', function () {
        $province = CustomerProvince::factory()->create();
        $province->delete();

        $response = $this->patchJson(route('setups.customers.provinces.restore', $province->id));

        $response->assertOk();
        $this->assertDatabaseHas('customer_provinces', [
            'id' => $province->id,
            'deleted_at' => null,
        ]);
    });

    it('can force delete a trashed customer province', function () {
        $province = CustomerProvince::factory()->create();
        $province->delete();

        $response = $this->deleteJson(route('setups.customers.provinces.force-delete', $province->id));

        $response->assertNoContent();
        $this->assertDatabaseMissing('customer_provinces', ['id' => $province->id]);
    });

    it('returns 404 when trying to restore non-existent trashed customer province', function () {
        $response = $this->patchJson(route('setups.customers.provinces.restore', 999));

        $response->assertNotFound();
    });

    it('returns 404 when trying to force delete non-existent trashed customer province', function () {
        $response = $this->deleteJson(route('setups.customers.provinces.force-delete', 999));

        $response->assertNotFound();
    });

    it('can search in description field', function () {
        CustomerProvince::factory()->create([
            'name' => 'Province One',
            'description' => 'Special retail description'
        ]);
        CustomerProvince::factory()->create([
            'name' => 'Province Two', 
            'description' => 'Regular wholesale description'
        ]);

        $response = $this->getJson(route('setups.customers.provinces.index', ['search' => 'retail']));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['description'])->toContain('retail');
    });

    it('sets created_by when creating customer province', function () {
        $data = [
            'name' => 'Test Customer Province',
            'description' => 'Test Description',
            'is_active' => true,
        ];

        $response = $this->postJson(route('setups.customers.provinces.store'), $data);

        $response->assertCreated();
        
        $this->assertDatabaseHas('customer_provinces', [
            'name' => 'Test Customer Province',
            'created_by' => $this->user->id,
        ]);
    });

    it('sets updated_by when updating customer province', function () {
        $province = CustomerProvince::factory()->create();
        $data = [
            'name' => 'Updated Customer Province',
            'description' => 'Updated Description',
        ];

        $response = $this->putJson(route('setups.customers.provinces.update', $province), $data);

        $response->assertOk();
        
        $this->assertDatabaseHas('customer_provinces', [
            'id' => $province->id,
            'name' => 'Updated Customer Province',
            'updated_by' => $this->user->id,
        ]);
    });
});
