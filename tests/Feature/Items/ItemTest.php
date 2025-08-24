<?php

use App\Models\Items\Item;
use App\Models\Setting;
use App\Models\Setups\ItemType;
use App\Models\Setups\ItemFamily;
use App\Models\Setups\ItemGroup;
use App\Models\Setups\ItemCategory;
use App\Models\Setups\ItemBrand;
use App\Models\Setups\ItemUnit;
use App\Models\Setups\Supplier;
use App\Models\Setups\TaxCode;
use App\Models\User;

uses()->group('api', 'setup', 'setup.items', 'items');

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user, 'sanctum');
    
    // Create default setting without seeder (much faster)
    Setting::create([
        'group_name' => 'items',
        'key_name' => 'code_counter', 
        'value' => '5000',
        'data_type' => 'number',
        'description' => 'Item code counter'
    ]);
    
    // Create related models for testing
    $this->itemType = ItemType::factory()->create();
    $this->itemFamily = ItemFamily::factory()->create();
    $this->itemGroup = ItemGroup::factory()->create();
    $this->itemCategory = ItemCategory::factory()->create();
    $this->itemBrand = ItemBrand::factory()->create();
    $this->itemUnit = ItemUnit::factory()->create();
    $this->supplier = Supplier::factory()->create();
    $this->taxCode = TaxCode::factory()->create();
    
    // Helper method for base item data
    $this->getBaseItemData = function ($overrides = []) {
        return array_merge([
            'short_name' => 'Test Item',
            'description' => 'Test item description',
            'item_type_id' => $this->itemType->id,
            'item_family_id' => $this->itemFamily->id,
            'item_unit_id' => $this->itemUnit->id,
            'tax_code_id' => $this->taxCode->id,
            'base_cost' => 100.00,
            'base_sell' => 120.00,
        ], $overrides);
    };
});

describe('Items API', function () {
    it('can list items', function () {
        Item::factory()->count(3)->create([
            'item_type_id' => $this->itemType->id,
            'item_family_id' => $this->itemFamily->id,
            'item_unit_id' => $this->itemUnit->id,
        ]);

        $response = $this->getJson(route('setups.items.index'));

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'code',
                        'short_name',
                        'description',
                    ]
                ],
                'pagination'
            ]);

        expect($response->json('data'))->toHaveCount(3);
    });

    it('can create an item with minimum required fields', function () {
        $data = [
            'short_name' => 'Test Item',
            'description' => 'A test item description',
            'item_type_id' => $this->itemType->id,
            'item_family_id' => $this->itemFamily->id,
            'item_unit_id' => $this->itemUnit->id,
            'tax_code_id' => $this->taxCode->id,
            'base_cost' => 100.00,
            'base_sell' => 120.00,
            'is_active' => true,
        ];

        $response = $this->postJson(route('setups.items.store'), $data);

        $response->assertCreated()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'code',
                    'short_name',
                    'base_cost',
                    'base_sell',
                    'is_active',
                ]
            ]);

        $this->assertDatabaseHas('items', [
            'short_name' => 'Test Item',
            'description' => 'A test item description',
            'base_cost' => 100.00,
            'base_sell' => 120.00,
            'is_active' => true,
        ]);

        // Check if code was auto-generated starting from 5000
        $item = Item::where('short_name', 'Test Item')->first();
        expect((int)$item->code)->toBeGreaterThanOrEqual(5000);
    });

    it('can create an item with all fields', function () {
        $data = [
            'short_name' => 'Complete Item',
            'description' => 'A complete test item with all fields',
            'item_type_id' => $this->itemType->id,
            'item_family_id' => $this->itemFamily->id,
            'item_group_id' => $this->itemGroup->id,
            'item_category_id' => $this->itemCategory->id,
            'item_brand_id' => $this->itemBrand->id,
            'item_unit_id' => $this->itemUnit->id,
            'supplier_id' => $this->supplier->id,
            'tax_code_id' => $this->taxCode->id,
            'base_cost' => 150.75,
            'base_sell' => 200.50,
            'starting_price' => 180.00,
            'starting_quantity' => 100,
            'low_quantity_alert' => 10,
            'cost_calculation' => 'weighted_average',
            'is_active' => true,
        ];

        $response = $this->postJson(route('setups.items.store'), $data);

        $response->assertCreated()
            ->assertJson([
                'data' => [
                    'short_name' => 'Complete Item',
                    'description' => 'A complete test item with all fields',
                    'base_cost' => 150.75,
                    'base_sell' => 200.50,
                    'starting_quantity' => 100,
                    'cost_calculation' => 'weighted_average',
                ]
            ]);

        // Verify code was auto-generated
        $item = Item::where('short_name', 'Complete Item')->first();
        expect($item->code)->not()->toBeNull();
        expect((int)$item->code)->toBeGreaterThanOrEqual(5000);

        $this->assertDatabaseHas('items', [
            'short_name' => 'Complete Item',
            'base_cost' => 150.75,
            'base_sell' => 200.50,
        ]);
    });

    it('can create an item with custom code', function () {
        $data = [
            'code' => 'CUSTOM-5000',  // Use current counter value (5000)
            'short_name' => 'Custom Code Item',
            'description' => 'Custom code item description',
            'item_type_id' => $this->itemType->id,
            'item_family_id' => $this->itemFamily->id,
            'item_unit_id' => $this->itemUnit->id,
            'tax_code_id' => $this->taxCode->id,
            'base_cost' => 100.00,
            'base_sell' => 120.00,
        ];

        $response = $this->postJson(route('setups.items.store'), $data);

        $response->assertCreated()
            ->assertJson([
                'data' => [
                    'code' => 'CUSTOM-5000',
                    'short_name' => 'Custom Code Item',
                ]
            ]);

        $this->assertDatabaseHas('items', [
            'code' => 'CUSTOM-5000',
            'short_name' => 'Custom Code Item',
        ]);
    });

    it('can show an item', function () {
        $item = Item::factory()->create([
            'item_type_id' => $this->itemType->id,
            'item_family_id' => $this->itemFamily->id,
            'item_unit_id' => $this->itemUnit->id,
        ]);

        $response = $this->getJson(route('setups.items.show', $item));

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'id' => $item->id,
                    'code' => $item->code,
                    'short_name' => $item->short_name,
                ]
            ]);
    });

    it('can update an item', function () {
        $item = Item::factory()->create([
            'item_type_id' => $this->itemType->id,
            'item_family_id' => $this->itemFamily->id,
            'item_unit_id' => $this->itemUnit->id,
        ]);
        
        $originalCode = $item->code;
        
        $data = [
            'short_name' => 'Updated Item',
            'description' => 'Updated description',
            'base_cost' => 200.00,
            'base_sell' => 250.00,
        ];

        $response = $this->putJson(route('setups.items.update', $item), $data);

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'code' => $originalCode, // Code should remain unchanged unless explicitly provided
                    'short_name' => 'Updated Item',
                    'description' => 'Updated description',
                    'base_cost' => 200.00,
                    'base_sell' => 250.00,
                ]
            ]);

        $this->assertDatabaseHas('items', [
            'id' => $item->id,
            'code' => $originalCode,
            'short_name' => 'Updated Item',
            'base_cost' => 200.00,
        ]);
    });

    it('can update an item code', function () {
        $item = Item::factory()->create([
            'code' => '5001',
            'item_type_id' => $this->itemType->id,
            'item_family_id' => $this->itemFamily->id,
            'item_unit_id' => $this->itemUnit->id,
        ]);

        $data = [
            'code' => 'NEW-5001',
            'short_name' => 'Updated Item',
        ];

        $response = $this->putJson(route('setups.items.update', $item), $data);

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'code' => 'NEW-5001',
                    'short_name' => 'Updated Item',
                ]
            ]);

        $this->assertDatabaseHas('items', [
            'id' => $item->id,
            'code' => 'NEW-5001',
            'short_name' => 'Updated Item',
        ]);
    });

    it('can delete an item', function () {
        $item = Item::factory()->create([
            'item_type_id' => $this->itemType->id,
            'item_family_id' => $this->itemFamily->id,
            'item_unit_id' => $this->itemUnit->id,
        ]);

        $response = $this->deleteJson(route('setups.items.destroy', $item));

        $response->assertNoContent();
        $this->assertSoftDeleted('items', ['id' => $item->id]);
    });

    it('validates required fields when creating', function () {
        $response = $this->postJson(route('setups.items.store'), []);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'description',
                'item_type_id',
                'item_unit_id',
                'tax_code_id',
            ]);
    });

    it('validates foreign key references', function () {
        $response = $this->postJson(route('setups.items.store'), ($this->getBaseItemData)([
            'item_type_id' => 99999,
            'item_family_id' => 99999,
            'item_group_id' => 99999,
            'item_category_id' => 99999,
            'item_brand_id' => 99999,
            'item_unit_id' => 99999,
            'supplier_id' => 99999,
            'tax_code_id' => 99999,
        ]));

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'item_type_id',
                'item_family_id',
                'item_group_id',
                'item_category_id',
                'item_brand_id',
                'item_unit_id',
                'supplier_id',
                'tax_code_id'
            ]);
    });

    it('validates unique code constraint', function () {
        Item::factory()->create([
            'code' => 'DUPLICATE-CODE',
            'item_type_id' => $this->itemType->id,
            'item_family_id' => $this->itemFamily->id,
            'item_unit_id' => $this->itemUnit->id,
        ]);

        $response = $this->postJson(route('setups.items.store'), [
            'code' => 'DUPLICATE-CODE',
            'short_name' => 'Duplicate Item',
            'item_type_id' => $this->itemType->id,
            'item_family_id' => $this->itemFamily->id,
            'item_unit_id' => $this->itemUnit->id,
            'base_cost' => 100.00,
            'base_sell' => 120.00,
        ]);

        $response->assertUnprocessable();
    });

    it('validates numeric code conflicts when using prefix/suffix', function () {
        Item::factory()->create([
            'code' => '5001',
            'item_type_id' => $this->itemType->id,
            'item_family_id' => $this->itemFamily->id,
            'item_unit_id' => $this->itemUnit->id,
        ]);

        // Try to create with same numeric part but different prefix
        $response = $this->postJson(route('setups.items.store'), [
            'code' => 'PREFIX-5001',
            'short_name' => 'Prefix Item',
            'item_type_id' => $this->itemType->id,
            'item_family_id' => $this->itemFamily->id,
            'item_unit_id' => $this->itemUnit->id,
            'base_cost' => 100.00,
            'base_sell' => 120.00,
        ]);

        $response->assertUnprocessable();
    });

    it('validates cost calculation enum values', function () {
        $response = $this->postJson(route('setups.items.store'), [
            'short_name' => 'Test Item',
            'item_type_id' => $this->itemType->id,
            'item_family_id' => $this->itemFamily->id,
            'item_unit_id' => $this->itemUnit->id,
            'base_cost' => 100.00,
            'base_sell' => 120.00,
            'cost_calculation' => 'INVALID_METHOD',
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['cost_calculation']);
    });

    it('validates numeric fields are positive', function () {
        $response = $this->postJson(route('setups.items.store'), [
            'short_name' => 'Test Item',
            'item_type_id' => $this->itemType->id,
            'item_family_id' => $this->itemFamily->id,
            'item_unit_id' => $this->itemUnit->id,
            'base_cost' => -50.00,
            'base_sell' => -60.00,
            'starting_price' => -10.00,
            'starting_quantity' => -5,
            'low_quantity_alert' => -1,
        ]);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'base_cost',
                'base_sell',
                'starting_price',
                'starting_quantity',
                'low_quantity_alert'
            ]);
    });

    it('can get next available code', function () {
        $response = $this->getJson(route('setups.items.next-code'));

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'code',
                    'is_available',
                    'message'
                ]
            ]);

        $code = $response->json('data.code');
        expect((int)$code)->toBeGreaterThanOrEqual(5000);
        expect($response->json('data.is_available'))->toBe(true);
    });

    it('can check code availability', function () {
        $response = $this->postJson(route('setups.items.check-code'), [
            'code' => 'AVAILABLE-CODE-5000'  // Use current counter value (5000)
        ]);

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'code' => 'AVAILABLE-CODE-5000',
                    'is_available' => true,
                ]
            ]);
    });

    it('can detect unavailable codes', function () {
        Item::factory()->create([
            'code' => 'TAKEN-CODE',
            'item_type_id' => $this->itemType->id,
            'item_family_id' => $this->itemFamily->id,
            'item_unit_id' => $this->itemUnit->id,
        ]);

        $response = $this->postJson(route('setups.items.check-code'), [
            'code' => 'TAKEN-CODE'
        ]);

        $response->assertOk()
            ->assertJson([
                'data' => [
                    'code' => 'TAKEN-CODE',
                    'is_available' => false,
                ]
            ]);

        expect($response->json('data.suggested_code'))->not()->toBeNull();
    });

    it('can search items by short name', function () {
        Item::factory()->create([
            'short_name' => 'Searchable Item',
            'item_type_id' => $this->itemType->id,
            'item_family_id' => $this->itemFamily->id,
            'item_unit_id' => $this->itemUnit->id,
        ]);
        Item::factory()->create([
            'short_name' => 'Another Item',
            'item_type_id' => $this->itemType->id,
            'item_family_id' => $this->itemFamily->id,
            'item_unit_id' => $this->itemUnit->id,
        ]);

        $response = $this->getJson(route('setups.items.index', ['search' => 'Searchable']));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['short_name'])->toBe('Searchable Item');
    });

    it('can search items by code', function () {
        Item::factory()->create([
            'code' => 'SEARCH-001',
            'short_name' => 'First Item',
            'item_type_id' => $this->itemType->id,
            'item_family_id' => $this->itemFamily->id,
            'item_unit_id' => $this->itemUnit->id,
        ]);
        Item::factory()->create([
            'code' => 'SEARCH-002',
            'short_name' => 'Second Item',
            'item_type_id' => $this->itemType->id,
            'item_family_id' => $this->itemFamily->id,
            'item_unit_id' => $this->itemUnit->id,
        ]);

        $response = $this->getJson(route('setups.items.index', ['search' => 'SEARCH-001']));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['code'])->toBe('SEARCH-001');
    });

    it('can filter by active status', function () {
        Item::factory()->create([
            'is_active' => true,
            'item_type_id' => $this->itemType->id,
            'item_family_id' => $this->itemFamily->id,
            'item_unit_id' => $this->itemUnit->id,
        ]);
        Item::factory()->create([
            'is_active' => false,
            'item_type_id' => $this->itemType->id,
            'item_family_id' => $this->itemFamily->id,
            'item_unit_id' => $this->itemUnit->id,
        ]);

        $response = $this->getJson(route('setups.items.index', ['is_active' => true]));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['is_active'])->toBe(true);
    });

    it('can filter by item type', function () {
        $productType = ItemType::factory()->create(['name' => 'Product']);
        $serviceType = ItemType::factory()->create(['name' => 'Service']);
        
        Item::factory()->create([
            'item_type_id' => $productType->id,
            'item_family_id' => $this->itemFamily->id,
            'item_unit_id' => $this->itemUnit->id,
        ]);
        Item::factory()->create([
            'item_type_id' => $serviceType->id,
            'item_family_id' => $this->itemFamily->id,
            'item_unit_id' => $this->itemUnit->id,
        ]);

        $response = $this->getJson(route('setups.items.index', ['item_type_id' => $productType->id]));

        $response->assertOk();
        $data = $response->json('data');
        // dd($data[0]);
        expect($data)->toHaveCount(1);
        expect($data[0]['item_type']['id'])->toBe($productType->id);
    });

    it('can filter by price range', function () {
        Item::factory()->create([
            'base_sell' => 50.00,
            'item_type_id' => $this->itemType->id,
            'item_family_id' => $this->itemFamily->id,
            'item_unit_id' => $this->itemUnit->id,
        ]);
        Item::factory()->create([
            'base_sell' => 150.00,
            'item_type_id' => $this->itemType->id,
            'item_family_id' => $this->itemFamily->id,
            'item_unit_id' => $this->itemUnit->id,
        ]);
        Item::factory()->create([
            'base_sell' => 250.00,
            'item_type_id' => $this->itemType->id,
            'item_family_id' => $this->itemFamily->id,
            'item_unit_id' => $this->itemUnit->id,
        ]);

        $response = $this->getJson(route('setups.items.index', [
            'min_price' => 100.00,
            'max_price' => 200.00
        ]));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['base_sell'])->toBe(150);
    });

    it('can filter by low stock', function () {
        Item::factory()->create([
            'starting_quantity' => 100,
            'low_quantity_alert' => 10,
            'item_type_id' => $this->itemType->id,
            'item_family_id' => $this->itemFamily->id,
            'item_unit_id' => $this->itemUnit->id,
        ]);
        Item::factory()->create([
            'starting_quantity' => 5,
            'low_quantity_alert' => 10,
            'item_type_id' => $this->itemType->id,
            'item_family_id' => $this->itemFamily->id,
            'item_unit_id' => $this->itemUnit->id,
        ]);

        $response = $this->getJson(route('setups.items.index', ['low_stock' => true]));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data)->toHaveCount(1);
        expect($data[0]['starting_quantity'])->toBe(5);
    });

    it('can sort items by name', function () {
        Item::factory()->create([
            'short_name' => 'Z Item',
            'item_type_id' => $this->itemType->id,
            'item_family_id' => $this->itemFamily->id,
            'item_unit_id' => $this->itemUnit->id,
        ]);
        Item::factory()->create([
            'short_name' => 'A Item',
            'item_type_id' => $this->itemType->id,
            'item_family_id' => $this->itemFamily->id,
            'item_unit_id' => $this->itemUnit->id,
        ]);

        $response = $this->getJson(route('setups.items.index', [
            'sort_by' => 'short_name',
            'sort_direction' => 'asc'
        ]));

        $response->assertOk();
        $data = $response->json('data');
        
        expect($data[0]['short_name'])->toBe('A Item');
        expect($data[1]['short_name'])->toBe('Z Item');
    });

    it('can list trashed items', function () {
        $item = Item::factory()->create([
            'item_type_id' => $this->itemType->id,
            'item_family_id' => $this->itemFamily->id,
            'item_unit_id' => $this->itemUnit->id,
        ]);
        $item->delete();

        $response = $this->getJson(route('setups.items.trashed'));

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'code',
                        'short_name',
                        'item_type',
                        'item_family',
                        'is_active',
                    ]
                ],
                'pagination'
            ]);

        expect($response->json('data'))->toHaveCount(1);
    });

    it('can restore a trashed item', function () {
        $item = Item::factory()->create([
            'item_type_id' => $this->itemType->id,
            'item_family_id' => $this->itemFamily->id,
            'item_unit_id' => $this->itemUnit->id,
        ]);
        $item->delete();

        $response = $this->patchJson(route('setups.items.restore', $item->id));

        $response->assertOk();
        $this->assertDatabaseHas('items', [
            'id' => $item->id,
            'deleted_at' => null,
        ]);
    });

    it('can force delete a trashed item', function () {
        $item = Item::factory()->create([
            'item_type_id' => $this->itemType->id,
            'item_family_id' => $this->itemFamily->id,
            'item_unit_id' => $this->itemUnit->id,
        ]);
        $item->delete();

        $response = $this->deleteJson(route('setups.items.force-delete', $item->id));

        $response->assertNoContent();
        $this->assertDatabaseMissing('items', ['id' => $item->id]);
    });

    it('can get item statistics', function () {
        Item::factory()->count(5)->create([
            'is_active' => true,
            'item_type_id' => $this->itemType->id,
            'item_family_id' => $this->itemFamily->id,
            'item_unit_id' => $this->itemUnit->id,
        ]);
        Item::factory()->count(2)->create([
            'is_active' => false,
            'item_type_id' => $this->itemType->id,
            'item_family_id' => $this->itemFamily->id,
            'item_unit_id' => $this->itemUnit->id,
        ]);

        $response = $this->getJson(route('setups.items.stats'));

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'total_items',
                    'active_items',
                    'inactive_items',
                    'trashed_items',
                    'low_stock_items',
                    'total_inventory_value',
                    'items_by_type',
                    'items_by_family',
                    'cost_calculation_breakdown',
                ]
            ]);

        $stats = $response->json('data');
        expect($stats['total_items'])->toBe(7);
        expect($stats['active_items'])->toBe(5);
        expect($stats['inactive_items'])->toBe(2);
    });

    it('can export items', function () {
        Item::factory()->count(3)->create([
            'item_type_id' => $this->itemType->id,
            'item_family_id' => $this->itemFamily->id,
            'item_unit_id' => $this->itemUnit->id,
        ]);

        $response = $this->getJson(route('setups.items.export'));

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    '*' => [
                        'code',
                        'short_name',
                        'description',
                        'item_type',
                        'item_family',
                        'item_group',
                        'item_category',
                        'item_brand',
                        'item_unit',
                        'supplier',
                        'tax_code',
                        'base_cost',
                        'base_sell',
                        'starting_price',
                        'starting_quantity',
                        'low_quantity_alert',
                        'cost_calculation',
                        'status',
                    ]
                ]
            ]);

        expect($response->json('data'))->toHaveCount(3);
    });

    it('auto-generates item codes when not provided', function () {
        // Create item without providing code
        $response = $this->postJson(route('setups.items.store'), ($this->getBaseItemData)([
            'short_name' => 'Auto Code Item',
        ]));
        
        $response->assertCreated();
        $code = $response->json('data.code');
        
        // Verify code was auto-generated
        expect($code)->not()->toBeNull();
        expect((int) $code)->toBeGreaterThanOrEqual(5000);
        
        // Verify item exists in database with generated code
        $this->assertDatabaseHas('items', [
            'short_name' => 'Auto Code Item',
            'code' => $code,
        ]);
    });

    it('sets created_by and updated_by fields automatically', function () {
        $item = Item::factory()->create([
            'short_name' => 'Test Item',
            'item_type_id' => $this->itemType->id,
            'item_family_id' => $this->itemFamily->id,
            'item_unit_id' => $this->itemUnit->id,
        ]);

        expect($item->created_by)->toBe($this->user->id);
        expect($item->updated_by)->toBe($this->user->id);

        // Test update tracking
        $item->update(['short_name' => 'Updated Item']);
        expect($item->fresh()->updated_by)->toBe($this->user->id);
    });

    it('returns 404 for non-existent item', function () {
        $response = $this->getJson(route('setups.items.show', 999));

        $response->assertNotFound();
    });

    it('can paginate items', function () {
        Item::factory()->count(7)->create([
            'item_type_id' => $this->itemType->id,
            'item_family_id' => $this->itemFamily->id,
            'item_unit_id' => $this->itemUnit->id,
        ]);

        $response = $this->getJson(route('setups.items.index', ['per_page' => 3]));

        $response->assertOk();
        $data = $response->json('data');
        $pagination = $response->json('pagination');
        
        expect($data)->toHaveCount(3);
        expect($pagination['total'])->toBe(7);
        expect($pagination['per_page'])->toBe(3);
        expect($pagination['last_page'])->toBe(3);
    });
});

describe('Item Code Generation Tests', function () {
    it('gets next code when current setting is 5001', function () {
        // Set code counter to 5001
        Setting::set('items', 'code_counter', 5001, 'number');
        
        $response = $this->getJson(route('setups.items.next-code'));
        
        $response->assertOk();
        $code = $response->json('data.code');
        
        expect((int) $code)->toBe(5001);
        expect($response->json('data.is_available'))->toBe(true);
    })->group('item-next-code');

    it('increments code counter when creating item with custom code a5001', function () {
        // Set code counter to 5001
        Setting::set('items', 'code_counter', 5001, 'number');
        
        // Create item with custom code that has same numeric part
        $response = $this->postJson(route('setups.items.store'), ($this->getBaseItemData)([
            'code' => 'a5001',
            'short_name' => 'Custom Code Item',
        ]));
        
        $response->assertCreated();
        
        // Get next available code - should be 5002
        $nextCodeResponse = $this->getJson(route('setups.items.next-code'));
        $nextCode = $nextCodeResponse->json('data.code');
        
        expect((int) $nextCode)->toBe(5002);
    })->group('item-next-code');

    it('increments to 5002 when item code a5001 is created via API', function () {
        // Set counter to 5001
        Setting::set('items', 'code_counter', 5001, 'number');

        // Create item with code a5001 via API (triggers validation)
        $response = $this->postJson(route('setups.items.store'), ($this->getBaseItemData)([
            'code' => 'a5001',
            'short_name' => 'Custom Code Item',
        ]));
        
        $response->assertCreated();
        
        // Get next code - should be 5002 since counter was incremented
        $nextResponse = $this->getJson(route('setups.items.next-code'));
        $nextCode = $nextResponse->json('data.code');
        
        expect((int) $nextCode)->toBe(5002);
    })->group('item-next-code');

    it('increments to 5003 when item code 5002a is created via API', function () {
        // Set counter to 5002
        Setting::set('items', 'code_counter', 5002, 'number');

        // Create item with code 5002a via API (triggers validation)
        $response = $this->postJson(route('setups.items.store'), ($this->getBaseItemData)([
            'code' => '5002a',
            'short_name' => 'Custom Code Item',
        ]));
        
        $response->assertCreated();
        
        // Get next code - should be 5003 since counter was incremented
        $nextResponse = $this->getJson(route('setups.items.next-code'));
        $nextCode = $nextResponse->json('data.code');
        
        expect((int) $nextCode)->toBe(5003);
    })->group('item-next-code');

    it('increments to 5004 when item code 50aa03 is created via API', function () {
        // Set counter to 5003
        Setting::set('items', 'code_counter', 5003, 'number');
        
        // Create item with code 50aa03 via API (triggers validation)
        $response = $this->postJson(route('setups.items.store'), ($this->getBaseItemData)([
            'code' => '50aa03',
            'short_name' => 'Custom Code Item',
        ]));
        
        $response->assertCreated();
        
        // Get next code - should be 5004 since counter was incremented
        $nextResponse = $this->getJson(route('setups.items.next-code'));
        $nextCode = $nextResponse->json('data.code');
        
        expect((int) $nextCode)->toBe(5004);
    })->group('item-next-code');

    it('validates that custom codes cannot be less than current counter', function () {
        // Set counter to 5005
        Setting::set('items', 'code_counter', 5005, 'number');
        
        // Try to create item with code less than counter
        $response = $this->postJson(route('setups.items.store'), ($this->getBaseItemData)([
            'code' => 'a5004', // numeric part 5004 is less than counter 5005
            'short_name' => 'Old Code Item',
        ]));
        
        // Should fail validation
        $response->assertUnprocessable();
    })->group('item-next-code');

    it('creates item with suggested code and updates counter correctly', function () {
        // Set counter to 5010
        Setting::set('items', 'code_counter', 5010, 'number');
        
        // Get suggested code
        $codeResponse = $this->getJson(route('setups.items.next-code'));
        $suggestedCode = $codeResponse->json('data.code');
        
        expect((int) $suggestedCode)->toBe(5010);
        
        // Create item using the suggested code
        $response = $this->postJson(route('setups.items.store'), ($this->getBaseItemData)([
            'code' => $suggestedCode,
            'short_name' => 'Suggested Code Item',
        ]));
        
        $response->assertCreated();
        
        // Get next code - should be 5011 now
        $nextCodeResponse = $this->getJson(route('setups.items.next-code'));
        $nextCode = $nextCodeResponse->json('data.code');
        
        expect((int) $nextCode)->toBe(5011);
    })->group('item-next-code');

    it('validates that custom codes cannot be greater than current counter', function () {
        // Set counter to 5015
        Setting::set('items', 'code_counter', 5015, 'number');
        
        // Try to create item with code greater than counter
        $response = $this->postJson(route('setups.items.store'), ($this->getBaseItemData)([
            'code' => 'prefix-5020', // numeric part 5020 is greater than counter 5015
            'short_name' => 'Future Code Item',
        ]));
        
        // Should fail validation
        $response->assertUnprocessable();
    })->group('item-next-code');

    it('validates that codes without numeric parts are rejected', function () {
        // Set counter to 5020
        Setting::set('items', 'code_counter', 5020, 'number');
        
        // Try to create item without numeric part
        $response = $this->postJson(route('setups.items.store'), ($this->getBaseItemData)([
            'code' => 'no-numbers-here',
            'short_name' => 'Non-numeric Code Item',
        ]));
        
        // Should fail validation
        $response->assertUnprocessable();
    })->group('item-next-code');

    it('auto-creates code counter setting when missing', function () {
        // Delete any existing code counter setting
        Setting::where('group_name', 'items')
            ->where('key_name', 'code_counter')
            ->delete();
        
        // Clear cache to ensure setting is gone
        Setting::clearCache();
        
        // Verify setting doesn't exist before test
        $settingBefore = Setting::where('group_name', 'items')
            ->where('key_name', 'code_counter')
            ->first();
        expect($settingBefore)->toBeNull();
        
        // Get next code - should auto-create setting with default value 5000
        $response = $this->getJson(route('setups.items.next-code'));
        
        $response->assertOk();
        $nextCode = $response->json('data.code');
        
        expect((int) $nextCode)->toBe(5000);
        
        // Verify setting was created
        $setting = Setting::where('group_name', 'items')
            ->where('key_name', 'code_counter')
            ->first();
            
        expect($setting)->not()->toBeNull();
        expect($setting->data_type)->toBe('number');
        expect($setting->value)->toBe('5000');
    })->group('item-next-code');

    it('handles counter progression correctly', function () {
        // Set counter to 5000
        Setting::set('items', 'code_counter', 5000, 'number');
        
        // Create item with current counter value
        $response = $this->postJson(route('setups.items.store'), ($this->getBaseItemData)([
            'code' => '5000',
            'short_name' => 'Counter Item',
        ]));
        
        $response->assertCreated();
        
        // Get next code - should be 5001
        $nextResponse = $this->getJson(route('setups.items.next-code'));
        $nextCode = $nextResponse->json('data.code');
        
        expect((int) $nextCode)->toBe(5001);
    })->group('item-next-code');

    it('extracts numeric parts correctly from various code formats', function () {
        // Test code with prefix
        $response = $this->postJson(route('setups.items.check-code'), ['code' => 'a5001']);
        $response->assertOk();
        expect($response->json('data.numeric_part'))->toBe('5001');
        
        // Test code with suffix
        $response = $this->postJson(route('setups.items.check-code'), ['code' => '5002a']);
        $response->assertOk();
        expect($response->json('data.numeric_part'))->toBe('5002');
        
        // Test code with mixed format
        $response = $this->postJson(route('setups.items.check-code'), ['code' => '50aa03']);
        $response->assertOk();
        expect($response->json('data.numeric_part'))->toBe('5003');
        
        // Test code with prefix and suffix
        $response = $this->postJson(route('setups.items.check-code'), ['code' => 'prefix-5004-suffix']);
        $response->assertOk();
        expect($response->json('data.numeric_part'))->toBe('5004');
        
        // Test numeric only
        $response = $this->postJson(route('setups.items.check-code'), ['code' => '999']);
        $response->assertOk();
        expect($response->json('data.numeric_part'))->toBe('999');
        
        // Test no numbers
        $response = $this->postJson(route('setups.items.check-code'), ['code' => 'no-numbers']);
        $response->assertOk();
        expect($response->json('data.numeric_part'))->toBe(null);
    })->group('item-next-code');
});