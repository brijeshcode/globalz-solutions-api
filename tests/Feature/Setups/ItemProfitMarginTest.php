<?php

use App\Models\Setups\ItemProfitMargin;
use App\Models\User;

uses()->group('api', 'setup', 'setup.items.profit_margin', 'item_profit_margin');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

describe('ItemProfitMargins API', function () {
    
    describe('GET /api/profit-margins', function () {
        it('can list profit margins', function () {
            ItemProfitMargin::factory()->count(3)->create();

            $response = $this->getJson(route('setups.items.profit-margins.index'));

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'message',
                    'data' => [
                        '*' => [
                            'id',
                            'name',
                            'margin_percentage',
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

        it('can filter by active status', function () {
            ItemProfitMargin::factory()->active()->create(['name' => 'Active Margin']);
            ItemProfitMargin::factory()->inactive()->create(['name' => 'Inactive Margin']);

            $response = $this->getJson(route('setups.items.profit-margins.index', ['is_active' => true]));

            $response->assertStatus(200);
            expect($response->json('data'))->toHaveCount(1);
            expect($response->json('data.0.name'))->toBe('Active Margin');
        });

        it('can search profit margins', function () {
            ItemProfitMargin::factory()->create(['name' => 'Premium Margin']);
            ItemProfitMargin::factory()->create(['name' => 'Standard Margin']);

            $response = $this->getJson(route('setups.items.profit-margins.index', ['search' => 'Premium']));

            $response->assertStatus(200);
            expect($response->json('data'))->toHaveCount(1);
            expect($response->json('data.0.name'))->toBe('Premium Margin');
        });

        it('can sort profit margins', function () {
            ItemProfitMargin::factory()->create(['name' => 'B Margin', 'margin_percentage' => 10.00]);
            ItemProfitMargin::factory()->create(['name' => 'A Margin', 'margin_percentage' => 20.00]);

            $response = $this->getJson(route('setups.items.profit-margins.index', [
                'sort_by' => 'margin_percentage',
                'sort_direction' => 'desc'
            ]));

            $response->assertStatus(200);
            expect($response->json('data.0.margin_percentage'))->toBe('20.00');
            expect($response->json('data.1.margin_percentage'))->toBe('10.00');
        });
    });

    describe('POST /api/profit-margins', function () {
        it('can create a profit margin', function () {
            $data = [
                'name' => 'Test Margin',
                'margin_percentage' => 25.50,
                'description' => 'Test description',
                'is_active' => true,
            ];

            $response = $this->postJson(route('setups.items.profit-margins.store'), $data);

            $response->assertStatus(201)
                ->assertJsonFragment([
                    'message' => 'Profit margin created successfully',
                    'name' => 'Test Margin',
                    'margin_percentage' => '25.50',
                    'description' => 'Test description',
                    'is_active' => true,
                ]);

            $this->assertDatabaseHas('item_profit_margins', [
                'name' => 'Test Margin',
                'margin_percentage' => 25.50,
                'description' => 'Test description',
                'is_active' => true,
            ]);
        });

        it('validates required fields', function () {
            $response = $this->postJson(route('setups.items.profit-margins.store'), []);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['name', 'margin_percentage']);
        });

        it('validates unique name', function () {
            ItemProfitMargin::factory()->create(['name' => 'Existing Margin']);

            $response = $this->postJson(route('setups.items.profit-margins.store'), [
                'name' => 'Existing Margin',
                'margin_percentage' => 15.00,
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['name']);
        });

        it('validates margin percentage range', function () {

            $response = $this->postJson(route('setups.items.profit-margins.store'), [
                'name' => 'Test Margin',
                'margin_percentage' => -5.00,
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['margin_percentage']);

            $response = $this->postJson(route('setups.items.profit-margins.store'), [
                'name' => 'Test Margin',
                'margin_percentage' => 1000.00,
            ]);
            
            $response->assertStatus(422)
                ->assertJsonValidationErrors(['margin_percentage']);
        });

        it('validates string length limits', function () {
            $response = $this->postJson(route('setups.items.profit-margins.store'), [
                'name' => str_repeat('a', 256),
                'margin_percentage' => 15.00,
                'description' => str_repeat('a', 501),
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['name', 'description']);
        });
    });

    describe('GET /api/profit-margins/{margin}', function () {
        it('can show a profit margin', function () {
            $margin = ItemProfitMargin::factory()->create([
                'name' => 'Test Margin',
                'margin_percentage' => 30.00,
            ]);

        $response = $this->getJson(route('setups.items.profit-margins.show', $margin->id));

            $response->assertStatus(200)
                ->assertJsonFragment([
                    'message' => 'Profit margin retrieved successfully',
                    'name' => 'Test Margin',
                    'margin_percentage' => '30.00',
                ]);
        });

        it('returns 404 for non-existent profit margin', function () { 
        $response = $this->getJson(route('setups.items.profit-margins.show', 999));

            $response->assertStatus(404);
        });
    });

    describe('PUT /api/profit-margins/{margin}', function () {
        it('can update a profit margin', function () {
            $margin = ItemProfitMargin::factory()->create([
                'name' => 'Original Margin',
                'margin_percentage' => 20.00,
            ]);

            $data = [
                'name' => 'Updated Margin',
                'margin_percentage' => 35.00,
                'description' => 'Updated description',
                'is_active' => false,
            ];

            $response = $this->putJson(route('setups.items.profit-margins.update', $margin->id), $data);

            $response->assertStatus(200)
                ->assertJsonFragment([
                    'message' => 'Profit margin updated successfully',
                    'name' => 'Updated Margin',
                    'margin_percentage' => '35.00',
                    'description' => 'Updated description',
                    'is_active' => false,
                ]);

            $this->assertDatabaseHas('item_profit_margins', [
                'id' => $margin->id,
                'name' => 'Updated Margin',
                'margin_percentage' => 35.00,
            ]);
        });

        it('validates unique name when updating', function () {
            $margin1 = ItemProfitMargin::factory()->create(['name' => 'Margin 1']);
            $margin2 = ItemProfitMargin::factory()->create(['name' => 'Margin 2']);

            $response = $this->putJson(route('setups.items.profit-margins.update', $margin2->id), [
                'name' => 'Margin 1',
                'margin_percentage' => 15.00,
            ]);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['name']);
        });

        it('allows updating profit margin with its own name', function () {
            $margin = ItemProfitMargin::factory()->create(['name' => 'Test Margin']);

            $response = $this->putJson(route('setups.items.profit-margins.update', $margin->id), [
                'name' => 'Test Margin', // Same name should be allowed
                'margin_percentage' => 15.00,
                'description' => 'Updated description',
            ]);

            $response->assertStatus(200)
                ->assertJsonFragment([
                    'name' => 'Test Margin',
                    'description' => 'Updated description',
                ]);
        });
    });

    describe('DELETE /api/profit-margins/{margin}', function () {
        it('can soft delete a profit margin', function () {
            $margin = ItemProfitMargin::factory()->create();

            $response = $this->deleteJson(route('setups.items.profit-margins.destroy', $margin));

            $response->assertStatus(204);

            $this->assertSoftDeleted('item_profit_margins', [
                'id' => $margin->id,
            ]);
        });
    });

    describe('GET /api/profit-margins/trashed', function () {
        it('can list trashed profit margins', function () {
            $activeMargin = ItemProfitMargin::factory()->create(['name' => 'Active Margin']);
            $trashedMargin = ItemProfitMargin::factory()->create(['name' => 'Trashed Margin']);
            $trashedMargin->delete();

            $response = $this->getJson(route('setups.items.profit-margins.trashed'));

            $response->assertStatus(200);
            $data = $response->json('data');
            
            expect($data)->toHaveCount(1);
            expect($data[0]['name'])->toBe('Trashed Margin');
        });

        it('can search trashed profit margins', function () {
            $margin1 = ItemProfitMargin::factory()->create(['name' => 'Trashed Premium']);
            $margin2 = ItemProfitMargin::factory()->create(['name' => 'Trashed Standard']);
            $margin1->delete();
            $margin2->delete();

            $response = $this->getJson(route('setups.items.profit-margins.trashed', ['search' => 'Premium']));

            $response->assertStatus(200);
            expect($response->json('data'))->toHaveCount(1);
            expect($response->json('data.0.name'))->toBe('Trashed Premium');
        });
    });

    describe('PATCH /api/profit-margins/{id}/restore', function () {
        it('can restore a trashed profit margin', function () {
            $margin = ItemProfitMargin::factory()->create(['name' => 'Restored Margin']);
            $margin->delete();

            $response = $this->patchJson(route('setups.items.profit-margins.restore', $margin->id));

            $response->assertStatus(200)
                ->assertJsonFragment([
                    'message' => 'Profit margin restored successfully',
                    'name' => 'Restored Margin',
                ]);

            $this->assertDatabaseHas('item_profit_margins', [
                'id' => $margin->id,
                'deleted_at' => null,
            ]);
        });

        it('returns 404 for non-trashed profit margin', function () {
            $margin = ItemProfitMargin::factory()->create();

            $response = $this->patchJson(route('setups.items.profit-margins.restore', $margin->id));

            $response->assertStatus(404);
        });
    });

    describe('DELETE /api/profit-margins/{id}/force-delete', function () {
        it('can permanently delete a trashed profit margin', function () {
            $margin = ItemProfitMargin::factory()->create(['name' => 'To Be Deleted']);
            $margin->delete();

            $response = $this->deleteJson(route('setups.items.profit-margins.force-delete',$margin->id));

            $response->assertStatus(204);

            $this->assertDatabaseMissing('item_profit_margins', [
                'id' => $margin->id,
            ]);
        });

        it('returns 404 for non-trashed profit margin', function () {
            $margin = ItemProfitMargin::factory()->create();

            $response = $this->deleteJson(route('setups.items.profit-margins.force-delete',$margin->id));

            $response->assertStatus(404);
        });
    });

    describe('Pagination', function () {
        it('paginates results correctly', function () {
            ItemProfitMargin::factory()->count(25)->create();

            $response = $this->getJson(route('setups.items.profit-margins.index',['per_page' => 10]));

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'data',
                    'pagination' => [
                        'current_page',
                        'per_page',
                        'total',
                        'last_page',
                        'from',
                        'to',
                        'has_more_pages',
                        'next_page_url',
                        'prev_page_url',
                        'first_page_url',
                        'last_page_url',
                    ]
                ]);

            expect($response->json('data'))->toHaveCount(10);
            expect($response->json('pagination.total'))->toBe(25);
            expect($response->json('pagination.last_page'))->toBe(3);
        });
    });

    describe('Authorization', function () {
        it('tracks created_by and updated_by', function () {
            $margin = ItemProfitMargin::factory()->create();

            $response = $this->getJson(route('setups.items.profit-margins.show',$margin->id));
            // $response = $this->getJson("/api/profit-margins/{$margin->id}");

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'data' => [
                        'created_by' => ['id', 'name'],
                        'updated_by' => ['id', 'name'],
                    ]
                ]);
        });
    });
});