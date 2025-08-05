<?php

use App\Models\Setups\ItemFamily;
use App\Models\User;

uses()->group('api', 'setup', 'setup.items.families', 'item_families');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'sanctum');
});

describe('Families API', function () {
    it('can list families', function () {
        ItemFamily::factory()->count(3)->create();

        $response = $this->getJson(route('setups.items.families.index'));

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

    it('can create a family', function () {
        $data = [
            'name' => 'Test Family',
            'description' => 'Test Description',
            'is_active' => true,
        ];

        $response = $this->postJson(route('setups.items.families.store'), $data);

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

        $this->assertDatabaseHas('item_families', [
            'name' => 'Test Family',
            'description' => 'Test Description',
        ]);
    });

    it('can show a family', function () {
        $family = ItemFamily::factory()->create();

        $response = $this->getJson(route('setups.items.families.show', $family));

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $family->id,
                    'name' => $family->name,
                ]
            ]);
    });

    it('can update a family', function () {
        $family = ItemFamily::factory()->create();
        $data = [
            'name' => 'Updated Family',
            'description' => 'Updated Description',
        ];

        $response = $this->putJson(route('setups.items.families.update', $family), $data);

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'name' => 'Updated Family',
                    'description' => 'Updated Description',
                ]
            ]);

        $this->assertDatabaseHas('item_families', [
            'id' => $family->id,
            'name' => 'Updated Family',
        ]);
    });

    it('can delete a family', function () {
        $family = ItemFamily::factory()->create();

        $response = $this->deleteJson(route('setups.items.families.destroy', $family));

        $response->assertNoContent();
        $this->assertSoftDeleted('item_families', ['id' => $family->id]);
    });

    it('validates required fields when creating', function () {
        $response = $this->postJson(route('setups.items.families.store'), []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('validates unique name when creating', function () {
        $existingFamily = ItemFamily::factory()->create();

        $response = $this->postJson(route('setups.items.families.store'), [
            'name' => $existingFamily->name,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('validates unique name when updating', function () {
        $family1 = ItemFamily::factory()->create();
        $family2 = ItemFamily::factory()->create();

        $response = $this->putJson(route('setups.items.families.update', $family1), [
            'name' => $family2->name,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['name']);
    });

    it('can search families', function () {
        ItemFamily::factory()->create(['name' => 'Searchable Family']);
        ItemFamily::factory()->create(['name' => 'Another Family']);

        $response = $this->getJson(route('setups.items.families.index', ['search' => 'Searchable']));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['name'])->toBe('Searchable Family');
    });

    it('can filter by active status', function () {
        ItemFamily::factory()->active()->create();
        ItemFamily::factory()->inactive()->create();

        $response = $this->getJson(route('setups.items.families.index', ['is_active' => true]));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['is_active'])->toBe(true);
    });

    it('can sort families', function () {
        ItemFamily::factory()->create(['name' => 'B Family']);
        ItemFamily::factory()->create(['name' => 'A Family']);

        $response = $this->getJson(route('setups.items.families.index', [
            'sort_by' => 'name',
            'sort_direction' => 'asc'
        ]));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data[0]['name'])->toBe('A Family');
        expect($data[1]['name'])->toBe('B Family');
    });

    it('returns 404 for non-existent family', function () {
        $response = $this->getJson(route('setups.items.families.show', 999));

        $response->assertNotFound();
    });

    it('can list trashed families', function () {
        $family = ItemFamily::factory()->create();
        $family->delete();

        $response = $this->getJson(route('setups.items.families.trashed'));

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

    it('can restore a trashed family', function () {
        $family = ItemFamily::factory()->create();
        $family->delete();

        $response = $this->patchJson(route('setups.items.families.restore', $family->id));

        $response->assertOk();
        $this->assertDatabaseHas('item_families', [
            'id' => $family->id,
            'deleted_at' => null,
        ]);
    });

    it('can force delete a trashed family', function () {
        $family = ItemFamily::factory()->create();
        $family->delete();

        $response = $this->deleteJson(route('setups.items.families.force-delete', $family->id));

        $response->assertNoContent();
        $this->assertDatabaseMissing('item_families', ['id' => $family->id]);
    });

    it('returns 404 when trying to restore non-existent trashed family', function () {
        $response = $this->patchJson(route('setups.items.families.restore', 999));

        $response->assertNotFound();
    });

    it('returns 404 when trying to force delete non-existent trashed family', function () {
        $response = $this->deleteJson(route('setups.items.families.force-delete', 999));

        $response->assertNotFound();
    });
});