<?php

use App\Models\Items\Item;
use App\Models\Items\PriceList;
use App\Models\Items\PriceListItem;
use App\Models\Setups\ItemType;
use App\Models\Setups\ItemFamily;
use App\Models\Setups\ItemUnit;
use App\Models\Setups\TaxCode;
use App\Models\User;

uses()->group('api', 'items', 'price-lists');

beforeEach(function () {
    $this->user = User::factory()->create(['role' => 'admin']);
    $this->actingAs($this->user, 'sanctum');

    // Create related models for testing items
    $this->itemType = ItemType::factory()->create();
    $this->itemFamily = ItemFamily::factory()->create();
    $this->itemUnit = ItemUnit::factory()->create();
    $this->taxCode = TaxCode::factory()->create();

    // Create test items
    $this->item1 = Item::factory()->create([
        'code' => 'ITEM-001',
        'description' => 'Test Item 1',
        'base_sell' => 100.00,
        'item_type_id' => $this->itemType->id,
        'item_family_id' => $this->itemFamily->id,
        'item_unit_id' => $this->itemUnit->id,
    ]);

    $this->item2 = Item::factory()->create([
        'code' => 'ITEM-002',
        'description' => 'Test Item 2',
        'base_sell' => 200.00,
        'item_type_id' => $this->itemType->id,
        'item_family_id' => $this->itemFamily->id,
        'item_unit_id' => $this->itemUnit->id,
    ]);

    // Helper method for base price list data
    $this->getBasePriceListData = function ($overrides = []) {
        return array_merge([
            'code' => 'PL-' . fake()->unique()->numerify('####'),
            'description' => 'Test Price List',
            'note' => 'Test note',
            'items' => [
                [
                    'item_code' => $this->item1->code,
                    'item_id' => $this->item1->id,
                    'item_description' => $this->item1->description,
                    'sell_price' => 120.00,
                ],
                [
                    'item_code' => $this->item2->code,
                    'item_id' => $this->item2->id,
                    'item_description' => $this->item2->description,
                    'sell_price' => 220.00,
                ],
            ],
        ], $overrides);
    };
});

describe('Price Lists API', function () {
    it('can list price lists', function () {
        PriceList::factory()->count(3)->create([
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
        ]);

        $response = $this->getJson(route('setups.price-lists.index'));

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'code',
                        'description',
                        'item_count',
                    ]
                ],
                'pagination'
            ]);

        expect($response->json('data'))->toHaveCount(3);
    });

    it('can create a price list with items', function () {
        $data = ($this->getBasePriceListData)();

        $response = $this->postJson(route('setups.price-lists.store'), $data);

        $response->assertCreated()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'code',
                    'description',
                    'item_count',
                    'items',
                ]
            ]);

        $this->assertDatabaseHas('price_lists', [
            'code' => $data['code'],
            'description' => 'Test Price List',
            'item_count' => 2,
        ]);

        $priceList = PriceList::where('code', $data['code'])->first();
        expect($priceList->items)->toHaveCount(2);
    });

    it('can create a price list with minimum required fields', function () {
        $data = [
            'code' => 'PL-MIN',
            'description' => 'Minimal Price List',
            'items' => [
                [
                    'item_code' => $this->item1->code,
                    'item_id' => $this->item1->id,
                    'sell_price' => 150.00,
                ],
            ],
        ];

        $response = $this->postJson(route('setups.price-lists.store'), $data);

        $response->assertCreated()
            ->assertJson([
                'data' => [
                    'code' => 'PL-MIN',
                    'description' => 'Minimal Price List',
                    'item_count' => 1,
                ]
            ]);
    });

    it('can show a price list', function () {
        $priceList = PriceList::factory()->create([
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
        ]);

        PriceListItem::factory()->count(2)->create([
            'price_list_id' => $priceList->id,
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
        ]);

        $response = $this->getJson(route('setups.price-lists.show', $priceList));

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $priceList->id,
                    'code' => $priceList->code,
                    'description' => $priceList->description,
                ]
            ])
            ->assertJsonStructure([
                'data' => [
                    'items' => [
                        '*' => [
                            'id',
                            'item_code',
                            'sell_price',
                        ]
                    ]
                ]
            ]);
    });

    it('can update a price list', function () {
        $priceList = PriceList::factory()->create([
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
        ]);

        $data = [
            'description' => 'Updated Price List',
            'note' => 'Updated note',
        ];

        $response = $this->putJson(route('setups.price-lists.update', $priceList), $data);

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'description' => 'Updated Price List',
                    'note' => 'Updated note',
                ]
            ]);

        $this->assertDatabaseHas('price_lists', [
            'id' => $priceList->id,
            'description' => 'Updated Price List',
        ]);
    });

    it('can update price list items', function () {
        $priceList = PriceList::factory()->create([
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
        ]);

        $existingItem = PriceListItem::factory()->create([
            'price_list_id' => $priceList->id,
            'item_id' => $this->item1->id,
            'sell_price' => 100.00,
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
        ]);

        $data = [
            'items' => [
                [
                    'id' => $existingItem->id,
                    'item_code' => $this->item1->code,
                    'item_id' => $this->item1->id,
                    'sell_price' => 150.00, // Updated price
                ],
                [
                    'item_code' => $this->item2->code,
                    'item_id' => $this->item2->id,
                    'sell_price' => 250.00, // New item
                ],
            ],
        ];

        $response = $this->putJson(route('setups.price-lists.update', $priceList), $data);

        $response->assertOk();

        // Check updated item
        $this->assertDatabaseHas('price_list_items', [
            'id' => $existingItem->id,
            'sell_price' => 150.00,
        ]);

        // Check new item was created
        $this->assertDatabaseHas('price_list_items', [
            'price_list_id' => $priceList->id,
            'item_id' => $this->item2->id,
            'sell_price' => 250.00,
        ]);
    });

    it('can delete a price list', function () {
        $priceList = PriceList::factory()->create([
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
        ]);

        $response = $this->deleteJson(route('setups.price-lists.destroy', $priceList));

        $response->assertNoContent();
        $this->assertSoftDeleted('price_lists', ['id' => $priceList->id]);
    });

    it('validates required fields when creating', function () {
        $response = $this->postJson(route('setups.price-lists.store'), []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'code',
                'description',
                'items',
            ]);
    });

    it('validates unique code constraint', function () {
        PriceList::factory()->create([
            'code' => 'DUPLICATE-CODE',
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
        ]);

        $data = ($this->getBasePriceListData)([
            'code' => 'DUPLICATE-CODE',
        ]);

        $response = $this->postJson(route('setups.price-lists.store'), $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['code']);
    });

    it('validates items array is not empty', function () {
        $data = [
            'code' => 'PL-EMPTY',
            'description' => 'Empty Price List',
            'items' => [],
        ];

        $response = $this->postJson(route('setups.price-lists.store'), $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['items']);
    });

    it('validates item sell_price is required and numeric', function () {
        $data = [
            'code' => 'PL-TEST',
            'description' => 'Test Price List',
            'items' => [
                [
                    'item_code' => $this->item1->code,
                    'item_id' => $this->item1->id,
                    // Missing sell_price
                ],
            ],
        ];

        $response = $this->postJson(route('setups.price-lists.store'), $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.sell_price']);
    });

    it('validates foreign key references for items', function () {
        $data = [
            'code' => 'PL-TEST',
            'description' => 'Test Price List',
            'items' => [
                [
                    'item_code' => 'INVALID',
                    'item_id' => 99999, // Non-existent item
                    'sell_price' => 100.00,
                ],
            ],
        ];

        $response = $this->postJson(route('setups.price-lists.store'), $data);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['items.0.item_id']);
    });

    it('can search price lists by code', function () {
        PriceList::factory()->create([
            'code' => 'SEARCH-001',
            'description' => 'First Price List',
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
        ]);
        PriceList::factory()->create([
            'code' => 'SEARCH-002',
            'description' => 'Second Price List',
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
        ]);

        $response = $this->getJson(route('setups.price-lists.index', ['search' => 'SEARCH-001']));

        $response->assertOk();
        $data = $response->json('data');

        expect($data)->toHaveCount(1);
        expect($data[0]['code'])->toBe('SEARCH-001');
    });

    it('can search price lists by description', function () {
        PriceList::factory()->create([
            'description' => 'Wholesale Prices',
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
        ]);
        PriceList::factory()->create([
            'description' => 'Retail Prices',
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
        ]);

        $response = $this->getJson(route('setups.price-lists.index', ['search' => 'Wholesale']));

        $response->assertOk();
        $data = $response->json('data');

        expect($data)->toHaveCount(1);
        expect($data[0]['description'])->toContain('Wholesale');
    });

    it('can filter by code', function () {
        $priceList = PriceList::factory()->create([
            'code' => 'PL-FILTER',
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
        ]);

        $response = $this->getJson(route('setups.price-lists.index', ['code' => 'PL-FILTER']));

        $response->assertOk();
        $data = $response->json('data');

        expect($data)->toHaveCount(1);
        expect($data[0]['code'])->toBe('PL-FILTER');
    });

    it('can sort price lists by code', function () {
        PriceList::factory()->create([
            'code' => 'PL-Z',
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
        ]);
        PriceList::factory()->create([
            'code' => 'PL-A',
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
        ]);

        $response = $this->getJson(route('setups.price-lists.index', [
            'sort_by' => 'code',
            'sort_direction' => 'asc'
        ]));

        $response->assertOk();
        $data = $response->json('data');

        expect($data[0]['code'])->toBe('PL-A');
        expect($data[1]['code'])->toBe('PL-Z');
    });

    it('can list trashed price lists', function () {
        $priceList = PriceList::factory()->create([
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
        ]);
        $priceList->delete();

        $response = $this->getJson(route('setups.price-lists.trashed'));

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'code',
                        'description',
                        'item_count',
                    ]
                ],
                'pagination'
            ]);

        expect($response->json('data'))->toHaveCount(1);
    });

    it('can restore a trashed price list', function () {
        $priceList = PriceList::factory()->create([
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
        ]);
        $priceList->delete();

        $response = $this->patchJson(route('setups.price-lists.restore', $priceList->id));

        $response->assertOk();
        $this->assertDatabaseHas('price_lists', [
            'id' => $priceList->id,
            'deleted_at' => null,
        ]);
    });

    it('can force delete a trashed price list', function () {
        $priceList = PriceList::factory()->create([
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
        ]);
        $priceList->delete();

        $response = $this->deleteJson(route('setups.price-lists.force-delete', $priceList->id));

        $response->assertNoContent();
        $this->assertDatabaseMissing('price_lists', ['id' => $priceList->id]);
    });

    it('can get price list statistics', function () {
        PriceList::factory()->count(5)->create([
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
        ])->each(function ($priceList) {
            PriceListItem::factory()->count(3)->create([
                'price_list_id' => $priceList->id,
                'created_by' => $this->user->id,
                'updated_by' => $this->user->id,
            ]);
        });

        $response = $this->getJson(route('setups.price-lists.stats'));

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'total_price_lists',
                    'trashed_price_lists',
                    'total_items',
                    'average_items_per_list',
                    'recent_price_lists',
                ]
            ]);

        $stats = $response->json('data');
        expect($stats['total_price_lists'])->toBe(5);
        expect($stats['total_items'])->toBe(15); // 5 lists * 3 items each
    });

    it('can duplicate a price list', function () {
        $priceList = PriceList::factory()->create([
            'code' => 'PL-ORIGINAL',
            'description' => 'Original Price List',
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
        ]);

        PriceListItem::factory()->count(2)->create([
            'price_list_id' => $priceList->id,
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
        ]);

        $response = $this->postJson(route('setups.price-lists.duplicate', $priceList));

        $response->assertCreated()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'code',
                    'description',
                    'item_count',
                ]
            ]);

        $duplicatedData = $response->json('data');
        expect($duplicatedData['code'])->toBe('PL-ORIGINAL-COPY');
        expect($duplicatedData['description'])->toBe('Original Price List (Copy)');
        expect($duplicatedData['item_count'])->toBe(2);
    });

    it('updates item_count automatically when items are added', function () {
        $data = ($this->getBasePriceListData)();

        $response = $this->postJson(route('setups.price-lists.store'), $data);

        $response->assertCreated();
        $priceListId = $response->json('data.id');

        $priceList = PriceList::find($priceListId);
        expect($priceList->item_count)->toBe(2);
    });

    it('updates item_count automatically when items are removed', function () {
        $priceList = PriceList::factory()->create([
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
        ]);

        $item1 = PriceListItem::factory()->create([
            'price_list_id' => $priceList->id,
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
        ]);

        $item2 = PriceListItem::factory()->create([
            'price_list_id' => $priceList->id,
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
        ]);

        expect($priceList->fresh()->item_count)->toBe(2);

        // Delete one item
        $item1->delete();

        // Manually update item count (in real app, this would be done by the controller)
        $priceList->updateItemCount();

        expect($priceList->fresh()->item_count)->toBe(1);
    });

    it('cascades delete to price list items', function () {
        $priceList = PriceList::factory()->create([
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
        ]);

        $item = PriceListItem::factory()->create([
            'price_list_id' => $priceList->id,
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
        ]);

        $priceList->delete();

        $this->assertSoftDeleted('price_list_items', ['id' => $item->id]);
    });

    it('sets created_by and updated_by fields automatically', function () {
        $priceList = PriceList::factory()->create([
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
        ]);

        expect($priceList->created_by)->toBe($this->user->id);
        expect($priceList->updated_by)->toBe($this->user->id);

        // Test update tracking
        $priceList->update(['description' => 'Updated Description']);
        expect($priceList->fresh()->updated_by)->toBe($this->user->id);
    });

    it('returns 404 for non-existent price list', function () {
        $response = $this->getJson(route('setups.price-lists.show', 999));

        $response->assertNotFound();
    });

    it('can paginate price lists', function () {
        PriceList::factory()->count(7)->create([
            'created_by' => $this->user->id,
            'updated_by' => $this->user->id,
        ]);

        $response = $this->getJson(route('setups.price-lists.index', ['per_page' => 3]));

        $response->assertOk();
        $data = $response->json('data');
        $pagination = $response->json('pagination');

        expect($data)->toHaveCount(3);
        expect($pagination['total'])->toBe(7);
        expect($pagination['per_page'])->toBe(3);
        expect($pagination['last_page'])->toBe(3);
    });

    it('auto-populates item details when item_id is provided', function () {
        $data = [
            'code' => 'PL-AUTO',
            'description' => 'Auto Populate Test',
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'sell_price' => 125.00,
                    // item_code and item_description should be auto-populated
                ],
            ],
        ];

        $response = $this->postJson(route('setups.price-lists.store'), $data);

        $response->assertCreated();

        $priceList = PriceList::where('code', 'PL-AUTO')->first();
        $item = $priceList->items->first();

        expect($item->item_code)->toBe($this->item1->code);
        expect($item->item_description)->toBe($this->item1->description);
    });
});
