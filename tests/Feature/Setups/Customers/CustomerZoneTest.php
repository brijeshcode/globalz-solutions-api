<?php

use App\Models\Setups\Customers\CustomerZone;
use App\Models\User;

uses()->group('api', 'setup', 'setup.customers', 'setup.customers.zones', 'customer_zones');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'sanctum');
});

describe('Customer Zones API', function () {
    it('can list customer zones', function () {
        CustomerZone::factory()->count(3)->create();

        $response = $this->getJson(route('setups.customers.zones.index'));

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

    it('can create a customer zone', function () {
        $data = [
            'name' => 'Test Customer Zone',
            'description' => 'Test Description',
            'is_active' => true,
        ];

        $response = $this->postJson(route('setups.customers.zones.store'), $data);

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

        $this->assertDatabaseHas('customer_zones', [
            'name' => 'Test Customer Zone',
            'description' => 'Test Description',
        ]);
    });

    it('can show a customer zone', function () {
        $zone = CustomerZone::factory()->create();

        $response = $this->getJson(route('setups.customers.zones.show', $zone));

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $zone->id,
                    'name' => $zone->name,
                ]
            ]);
    });

    it('can update a customer zone', function () {
        $zone = CustomerZone::factory()->create();
        $data = [
            'name' => 'Updated Customer Zone',
            'description' => 'Updated Description',
        ];

        $response = $this->putJson(route('setups.customers.zones.update', $zone), $data);

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'name' => 'Updated Customer Zone',
                    'description' => 'Updated Description',
                ]
            ]);

        $this->assertDatabaseHas('customer_zones', [
            'id' => $zone->id,
            'name' => 'Updated Customer Zone',
        ]);
    });

    it('can delete a customer zone', function () {
        $zone = CustomerZone::factory()->create();

        $response = $this->deleteJson(route('setups.customers.zones.destroy', $zone));

        $response->assertNoContent();
        $this->assertSoftDeleted('customer_zones', ['id' => $zone->id]);
    });

    it('validates required fields when creating', function () {
        $response = $this->postJson(route('setups.customers.zones.store'), []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('validates unique name when creating', function () {
        $existingZone = CustomerZone::factory()->create();

        $response = $this->postJson(route('setups.customers.zones.store'), [
            'name' => $existingZone->name,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('validates unique name when updating', function () {
        $zone1 = CustomerZone::factory()->create();
        $zone2 = CustomerZone::factory()->create();

        $response = $this->putJson(route('setups.customers.zones.update', $zone1), [
            'name' => $zone2->name,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('allows updating customer zone with its own name', function () {
        $zone = CustomerZone::factory()->create(['name' => 'Test Customer Zone']);

        $response = $this->putJson(route('setups.customers.zones.update', $zone), [
            'name' => 'Test Customer Zone', // Same name should be allowed
            'description' => 'Updated description',
        ]);

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'name' => 'Test Customer Zone',
                    'description' => 'Updated description',
                ]
            ]);
    });

    it('can search customer zones', function () {
        CustomerZone::factory()->create(['name' => 'Searchable Customer Zone']);
        CustomerZone::factory()->create(['name' => 'Another Customer Zone']);

        $response = $this->getJson(route('setups.customers.zones.index', ['search' => 'Searchable']));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['name'])->toBe('Searchable Customer Zone');
    });

    it('can filter by active status', function () {
        CustomerZone::factory()->active()->create();
        CustomerZone::factory()->inactive()->create();

        $response = $this->getJson(route('setups.customers.zones.index', ['is_active' => true]));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['is_active'])->toBe(true);
    });

    it('can sort customer zones', function () {
        CustomerZone::factory()->create(['name' => 'B Customer Zone']);
        CustomerZone::factory()->create(['name' => 'A Customer Zone']);

        $response = $this->getJson(route('setups.customers.zones.index', [
            'sort_by' => 'name',
            'sort_direction' => 'asc'
        ]));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data[0]['name'])->toBe('A Customer Zone');
        expect($data[1]['name'])->toBe('B Customer Zone');
    });

    it('returns 404 for non-existent customer zone', function () {
        $response = $this->getJson(route('setups.customers.zones.show', 999));

        $response->assertNotFound();
    });

    it('can list trashed customer zones', function () {
        $zone = CustomerZone::factory()->create();
        $zone->delete();

        $response = $this->getJson(route('setups.customers.zones.trashed'));

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

    it('can restore a trashed customer zone', function () {
        $zone = CustomerZone::factory()->create();
        $zone->delete();

        $response = $this->patchJson(route('setups.customers.zones.restore', $zone->id));

        $response->assertOk();
        $this->assertDatabaseHas('customer_zones', [
            'id' => $zone->id,
            'deleted_at' => null,
        ]);
    });

    it('can force delete a trashed customer zone', function () {
        $zone = CustomerZone::factory()->create();
        $zone->delete();

        $response = $this->deleteJson(route('setups.customers.zones.force-delete', $zone->id));

        $response->assertNoContent();
        $this->assertDatabaseMissing('customer_zones', ['id' => $zone->id]);
    });

    it('returns 404 when trying to restore non-existent trashed customer zone', function () {
        $response = $this->patchJson(route('setups.customers.zones.restore', 999));

        $response->assertNotFound();
    });

    it('returns 404 when trying to force delete non-existent trashed customer zone', function () {
        $response = $this->deleteJson(route('setups.customers.zones.force-delete', 999));

        $response->assertNotFound();
    });

    it('can search in description field', function () {
        CustomerZone::factory()->create([
            'name' => 'Zone One',
            'description' => 'Special retail description'
        ]);
        CustomerZone::factory()->create([
            'name' => 'Zone Two', 
            'description' => 'Regular wholesale description'
        ]);

        $response = $this->getJson(route('setups.customers.zones.index', ['search' => 'retail']));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['description'])->toContain('retail');
    });

    it('sets created_by when creating customer zone', function () {
        $data = [
            'name' => 'Test Customer Zone',
            'description' => 'Test Description',
            'is_active' => true,
        ];

        $response = $this->postJson(route('setups.customers.zones.store'), $data);

        $response->assertCreated();
        
        $this->assertDatabaseHas('customer_zones', [
            'name' => 'Test Customer Zone',
            'created_by' => $this->user->id,
        ]);
    });

    it('sets updated_by when updating customer zone', function () {
        $zone = CustomerZone::factory()->create();
        $data = [
            'name' => 'Updated Customer Zone',
            'description' => 'Updated Description',
        ];

        $response = $this->putJson(route('setups.customers.zones.update', $zone), $data);

        $response->assertOk();
        
        $this->assertDatabaseHas('customer_zones', [
            'id' => $zone->id,
            'name' => 'Updated Customer Zone',
            'updated_by' => $this->user->id,
        ]);
    });
});
