<?php

use App\Models\Setups\ItemUnit;
use App\Models\User;
uses()->group('api', 'setup', 'setup.units', 'item_units');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'sanctum');
});

describe('Unit API', function () {
    
    it('can list units with pagination', function () {
        ItemUnit::factory()->count(5)->create();

        $response = $this->getJson('/api/setups/items/units');

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

        $response = $this->postJson('/api/setups/items/units', $unitData);

        $response->assertCreated()
            ->assertJsonFragment(['name' => 'Test Unit']);

        $this->assertDatabaseHas('item_units', [
            'name' => 'Test Unit',
            'created_by' => $this->user->id,
        ]);
    });

    it('can show a specific unit', function () {
        $unit = ItemUnit::factory()->create(['name' => 'Test Unit']);

        $response = $this->getJson("/api/setups/items/units/{$unit->id}");

        $response->assertOk()
            ->assertJsonFragment(['name' => 'Test Unit']);
    });

    it('can update a unit', function () {
        $unit = ItemUnit::factory()->create(['name' => 'Original Name']);

        $updateData = [
            'name' => 'Updated Name',
            'short_name' => 'UN',
            'is_active' => false,
        ];

        $response = $this->putJson("/api/setups/items/units/{$unit->id}", $updateData);

        $response->assertOk()
            ->assertJsonFragment(['name' => 'Updated Name']);

        $this->assertDatabaseHas('item_units', [
            'id' => $unit->id,
            'name' => 'Updated Name',
            'updated_by' => $this->user->id,
        ]);
    });

    it('can soft delete a unit', function () {
        $unit = ItemUnit::factory()->create();

        $response = $this->deleteJson("/api/setups/items/units/{$unit->id}");

        $response->assertNoContent();
        $this->assertSoftDeleted('item_units', ['id' => $unit->id]);
    });

    it('can filter units by active status', function () {
        ItemUnit::factory()->active()->count(3)->create();
        ItemUnit::factory()->inactive()->count(2)->create();

        $response = $this->getJson('/api/setups/items/units?is_active=1');

        $response->assertOk();
        $data = $response->json('data');
        
        expect(count($data))->toBe(3);
        collect($data)->each(fn($unit) => expect($unit['is_active'])->toBeTrue());
    });

    it('can search units by name', function () {
        ItemUnit::factory()->create(['name' => 'Pieces']);
        ItemUnit::factory()->create(['name' => 'Kilograms']);
        ItemUnit::factory()->create(['name' => 'Boxes']);

        $response = $this->getJson('/api/setups/items/units?search=Piece');

        $response->assertOk();
        $data = $response->json('data');
        
        expect(count($data))->toBe(1);
        expect($data[0]['name'])->toBe('Pieces');
    });

    it('can sort units by name', function () {
        ItemUnit::factory()->create(['name' => 'Zebra']);
        ItemUnit::factory()->create(['name' => 'Alpha']);
        ItemUnit::factory()->create(['name' => 'Beta']);

        $response = $this->getJson('/api/setups/items/units?sort_by=name&sort_direction=asc');

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data[0]['name'])->toBe('Alpha');
        expect($data[1]['name'])->toBe('Beta');
        expect($data[2]['name'])->toBe('Zebra');
    });

    it('can get active units only', function () {
        ItemUnit::factory()->active()->count(3)->create();
        ItemUnit::factory()->inactive()->count(2)->create();

        $response = $this->getJson('/api/setups/items/units/active');

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
        $response = $this->postJson('/api/setups/items/units', [
            'short_name' => 'TU',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('requires unique name', function () {
        ItemUnit::factory()->create(['name' => 'Existing Unit']);

        $response = $this->postJson('/api/setups/items/units', [
            'name' => 'Existing Unit',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('validates short_name length', function () {
        $response = $this->postJson('/api/setups/items/units', [
            'name' => 'Test Unit',
            'short_name' => 'This is way too long',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['short_name']);
    });

    it('validates unique name when updating', function () {
        $unit1 = ItemUnit::factory()->create();
        $unit2 = ItemUnit::factory()->create();

        $response = $this->putJson("/api/setups/items/units/{$unit1->id}", [
            'name' => $unit2->name,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('allows updating unit with its own name', function () {
        $unit = ItemUnit::factory()->create(['name' => 'Test Unit']);

        $response = $this->putJson("/api/setups/items/units/{$unit->id}", [
            'name' => 'Test Unit', // Same name should be allowed
            'short_name' => 'TU',
            'description' => 'Updated description',
        ]);

        $response->assertOk()
            ->assertJsonFragment([
                'name' => 'Test Unit',
                'description' => 'Updated description',
            ]);
    });
});

describe('Unit Authentication', function () {
    
    it('requires authentication', function () {
        // Create new test instance without acting as user
        $this->app['auth']->forgetGuards();
        
        $response = $this->getJson('/api/setups/items/units');

        $response->assertUnauthorized();
    });
});