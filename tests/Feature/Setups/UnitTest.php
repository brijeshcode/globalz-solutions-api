<?php

use App\Models\Unit;
use App\Models\User;
uses()->group('api', 'setup', 'setup.units', 'units');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'sanctum');
});

describe('Unit API', function () {
    
    it('can list units with pagination', function () {
        Unit::factory()->count(5)->create();

        $response = $this->getJson('/api/setups/units');

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'name',
                        'short_name',
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

    it('can create a unit', function () {
        $unitData = [
            'name' => 'Test Unit',
            'short_name' => 'TU',
            'description' => 'Test description',
            'is_active' => true,
        ];

        $response = $this->postJson('/api/setups/units', $unitData);

        $response->assertCreated()
            ->assertJsonFragment(['name' => 'Test Unit']);

        $this->assertDatabaseHas('units', [
            'name' => 'Test Unit',
            'created_by' => $this->user->id,
        ]);
    });

    it('can show a specific unit', function () {
        $unit = Unit::factory()->create(['name' => 'Test Unit']);

        $response = $this->getJson("/api/setups/units/{$unit->id}");

        $response->assertOk()
            ->assertJsonFragment(['name' => 'Test Unit']);
    });

    it('can update a unit', function () {
        $unit = Unit::factory()->create(['name' => 'Original Name']);

        $updateData = [
            'name' => 'Updated Name',
            'short_name' => 'UN',
            'is_active' => false,
        ];

        $response = $this->putJson("/api/setups/units/{$unit->id}", $updateData);

        $response->assertOk()
            ->assertJsonFragment(['name' => 'Updated Name']);

        $this->assertDatabaseHas('units', [
            'id' => $unit->id,
            'name' => 'Updated Name',
            'updated_by' => $this->user->id,
        ]);
    });

    it('can soft delete a unit', function () {
        $unit = Unit::factory()->create();

        $response = $this->deleteJson("/api/setups/units/{$unit->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted('units', ['id' => $unit->id]);
    });

    it('can filter units by active status', function () {
        Unit::factory()->active()->count(3)->create();
        Unit::factory()->inactive()->count(2)->create();

        $response = $this->getJson('/api/setups/units?is_active=1');

        $response->assertOk();
        $data = $response->json('data');
        
        expect(count($data))->toBe(3);
        collect($data)->each(fn($unit) => expect($unit['is_active'])->toBeTrue());
    });

    it('can search units by name', function () {
        Unit::factory()->create(['name' => 'Pieces']);
        Unit::factory()->create(['name' => 'Kilograms']);
        Unit::factory()->create(['name' => 'Boxes']);

        $response = $this->getJson('/api/setups/units?search=Piece');

        $response->assertOk();
        $data = $response->json('data');
        
        expect(count($data))->toBe(1);
        expect($data[0]['name'])->toBe('Pieces');
    });

    it('can sort units by name', function () {
        Unit::factory()->create(['name' => 'Zebra']);
        Unit::factory()->create(['name' => 'Alpha']);
        Unit::factory()->create(['name' => 'Beta']);

        $response = $this->getJson('/api/setups/units?sort_by=name&sort_direction=asc');

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data[0]['name'])->toBe('Alpha');
        expect($data[1]['name'])->toBe('Beta');
        expect($data[2]['name'])->toBe('Zebra');
    });

    it('can get active units only', function () {
        Unit::factory()->active()->count(3)->create();
        Unit::factory()->inactive()->count(2)->create();

        $response = $this->getJson('/api/setups/units/active');

        $response->assertOk();
        $data = $response->json('data');
        
        expect(count($data))->toBe(3);
        collect($data)->each(function ($unit) {
            expect($unit)->toHaveKeys(['id', 'name', 'short_name']);
        });
    });
});

describe('Unit Validation', function () {
    
    it('requires name field', function () {
        $response = $this->postJson('/api/setups/units', [
            'short_name' => 'TU',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('requires unique name', function () {
        Unit::factory()->create(['name' => 'Existing Unit']);

        $response = $this->postJson('/api/setups/units', [
            'name' => 'Existing Unit',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('validates short_name length', function () {
        $response = $this->postJson('/api/setups/units', [
            'name' => 'Test Unit',
            'short_name' => 'This is way too long',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['short_name']);
    });

    it('allows update with same name', function () {
        $unit = Unit::factory()->create(['name' => 'Test Unit']);

        $response = $this->putJson("/api/setups/units/{$unit->id}", [
            'name' => 'Test Unit',
            'short_name' => 'TU',
        ]);

        $response->assertOk();
    });
});

describe('Unit Authentication', function () {
    
    it('requires authentication', function () {
        // Create new test instance without acting as user
        $this->app['auth']->forgetGuards();
        
        $response = $this->getJson('/api/setups/units');

        $response->assertUnauthorized();
    });
});