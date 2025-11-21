<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Items\ItemAdjust;
use App\Models\Items\ItemAdjustItem;
use App\Models\Items\Item;
use App\Models\Setups\Warehouse;
use App\Models\Inventory\Inventory;
use App\Models\User;
use App\Models\Setting;

uses(RefreshDatabase::class)->group('api', 'items', 'item-adjusts');

beforeEach(function () {
    // Create item adjust code counter setting
    Setting::create([
        'group_name' => 'item_adjusts',
        'key_name' => 'code_counter',
        'value' => '1000',
        'data_type' => 'number',
        'description' => 'Item adjust code counter starting from 1000'
    ]);

    // Create warehouse for testing
    $this->warehouse = Warehouse::factory()->create(['name' => 'Test Warehouse']);

    // Create test items
    $this->item1 = Item::factory()->create([
        'code' => 'ITEM001',
        'short_name' => 'Test Item 1',
    ]);
    $this->item2 = Item::factory()->create([
        'code' => 'ITEM002',
        'short_name' => 'Test Item 2',
    ]);

    // Helper method for base item adjust data
    $this->getBaseItemAdjustData = function ($overrides = []) {
        return array_merge([
            'date' => '2025-01-15',
            'type' => 'Add',
            'warehouse_id' => $this->warehouse->id,
        ], $overrides);
    };

    // Helper method to create item adjust via API
    $this->createItemAdjustViaApi = function ($overrides = []) {
        $itemAdjustData = ($this->getBaseItemAdjustData)($overrides);

        // Ensure items are provided if not in overrides
        if (!isset($itemAdjustData['items'])) {
            $itemAdjustData['items'] = [
                [
                    'item_id' => $this->item1->id,
                    'quantity' => 10,
                ]
            ];
        }

        $response = $this->postJson(route('items.adjusts.store'), $itemAdjustData);
        $response->assertCreated();

        $itemAdjustId = $response->json('data.id');
        return ItemAdjust::find($itemAdjustId);
    };

    // Helper to setup initial inventory
    $this->setupInitialInventory = function (int $itemId, int $warehouseId, float $quantity) {
        Inventory::updateOrCreate(
            ['warehouse_id' => $warehouseId, 'item_id' => $itemId],
            ['quantity' => $quantity]
        );
    };
});

describe('Item Adjusts API', function () {
    beforeEach(function () {
        $this->user = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($this->user, 'sanctum');
    });

    it('can list item adjusts', function () {
        ItemAdjust::factory()->count(3)->create([
            'warehouse_id' => $this->warehouse->id,
        ]);

        $response = $this->getJson(route('items.adjusts.index'));

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'code',
                        'date',
                        'type',
                        'warehouse',
                    ]
                ],
                'pagination'
            ]);

        expect($response->json('data'))->toHaveCount(3);
    });

    it('can create item adjust with Add type and updates inventory', function () {
        // Setup initial inventory
        ($this->setupInitialInventory)($this->item1->id, $this->warehouse->id, 100);
        ($this->setupInitialInventory)($this->item2->id, $this->warehouse->id, 200);

        $itemAdjustData = ($this->getBaseItemAdjustData)([
            'type' => 'Add',
            'note' => 'Test adjustment with multiple items',
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'quantity' => 10,
                    'note' => 'First adjusted item'
                ],
                [
                    'item_id' => $this->item2->id,
                    'quantity' => 15,
                    'note' => 'Second adjusted item'
                ]
            ]
        ]);

        $response = $this->postJson(route('items.adjusts.store'), $itemAdjustData);

        $response->assertCreated()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'code',
                    'type',
                    'items',
                    'warehouse'
                ]
            ]);

        // Verify item adjust was created
        $this->assertDatabaseHas('item_adjusts', [
            'warehouse_id' => $this->warehouse->id,
            'type' => 'Add',
        ]);

        // Verify item adjust items were created
        $itemAdjust = ItemAdjust::latest()->first();
        expect($itemAdjust->itemAdjustItems)->toHaveCount(2);

        // Verify inventory was updated (increased for Add type)
        $inventory1 = Inventory::where('warehouse_id', $this->warehouse->id)
            ->where('item_id', $this->item1->id)
            ->first();
        expect($inventory1->quantity)->toBe('110.0000'); // 100 + 10

        $inventory2 = Inventory::where('warehouse_id', $this->warehouse->id)
            ->where('item_id', $this->item2->id)
            ->first();
        expect($inventory2->quantity)->toBe('215.0000'); // 200 + 15
    });

    it('can create item adjust with Subtract type and updates inventory', function () {
        // Setup initial inventory
        ($this->setupInitialInventory)($this->item1->id, $this->warehouse->id, 100);
        ($this->setupInitialInventory)($this->item2->id, $this->warehouse->id, 200);

        $itemAdjustData = ($this->getBaseItemAdjustData)([
            'type' => 'Subtract',
            'note' => 'Test subtract adjustment',
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'quantity' => 10,
                ],
                [
                    'item_id' => $this->item2->id,
                    'quantity' => 15,
                ]
            ]
        ]);

        $response = $this->postJson(route('items.adjusts.store'), $itemAdjustData);

        $response->assertCreated();

        // Verify inventory was updated (decreased for Subtract type)
        $inventory1 = Inventory::where('warehouse_id', $this->warehouse->id)
            ->where('item_id', $this->item1->id)
            ->first();
        expect($inventory1->quantity)->toBe('90.0000'); // 100 - 10

        $inventory2 = Inventory::where('warehouse_id', $this->warehouse->id)
            ->where('item_id', $this->item2->id)
            ->first();
        expect($inventory2->quantity)->toBe('185.0000'); // 200 - 15
    });

    it('can show item adjust with all relationships', function () {
        ($this->setupInitialInventory)($this->item1->id, $this->warehouse->id, 50);

        $itemAdjust = ($this->createItemAdjustViaApi)();

        $response = $this->getJson(route('items.adjusts.show', $itemAdjust));

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'code',
                    'type',
                    'warehouse' => ['id', 'name'],
                    'items' => [
                        '*' => [
                            'id',
                            'item' => ['id', 'code', 'short_name'],
                            'quantity',
                        ]
                    ]
                ]
            ]);
    });

    it('can update item adjust with item sync', function () {
        // Setup initial inventory
        ($this->setupInitialInventory)($this->item1->id, $this->warehouse->id, 100);
        ($this->setupInitialInventory)($this->item2->id, $this->warehouse->id, 200);

        // Create initial item adjust via API
        $itemAdjust = ($this->createItemAdjustViaApi)([
            'type' => 'Add',
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'quantity' => 5,
                ]
            ]
        ]);

        $existingItem = $itemAdjust->itemAdjustItems->first();

        // Update item adjust data
        $updateData = [
            'items' => [
                // Update existing item
                [
                    'id' => $existingItem->id,
                    'item_id' => $this->item1->id,
                    'quantity' => 8,
                    'note' => 'Updated item'
                ],
                // Add new item
                [
                    'item_id' => $this->item2->id,
                    'quantity' => 10,
                    'note' => 'New item added'
                ]
            ]
        ];

        $response = $this->putJson(route('items.adjusts.update', $itemAdjust), $updateData);

        $response->assertOk();

        // Verify item adjust was updated
        $itemAdjust->refresh();

        // Verify items were synced
        expect($itemAdjust->itemAdjustItems)->toHaveCount(2);

        // Verify existing item was updated
        $existingItem->refresh();
        expect($existingItem->quantity)->toBe('8.0000');

        // Verify inventory was updated correctly
        // Initial: 100, first adjust: +5, update to 8: additional +3 = 108
        $inventory1 = Inventory::where('warehouse_id', $this->warehouse->id)
            ->where('item_id', $this->item1->id)
            ->first();
        expect($inventory1->quantity)->toBe('108.0000');

        // Verify new item was added to inventory
        $inventory2 = Inventory::where('warehouse_id', $this->warehouse->id)
            ->where('item_id', $this->item2->id)
            ->first();
        expect($inventory2->quantity)->toBe('210.0000'); // 200 + 10
    });

    it('auto-generates item adjust codes', function () {
        ($this->setupInitialInventory)($this->item1->id, $this->warehouse->id, 50);

        $itemAdjustData = ($this->getBaseItemAdjustData)([
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'quantity' => 5,
                ]
            ]
        ]);

        $response = $this->postJson(route('items.adjusts.store'), $itemAdjustData);

        $response->assertCreated();

        $itemAdjust = ItemAdjust::where('warehouse_id', $this->warehouse->id)->first();
        expect($itemAdjust->code)->not()->toBeNull();
        expect($itemAdjust->prefix)->toBe('ADJ');
    });

    it('can soft delete item adjust', function () {
        ($this->setupInitialInventory)($this->item1->id, $this->warehouse->id, 50);

        $itemAdjust = ItemAdjust::factory()->create([
            'warehouse_id' => $this->warehouse->id,
        ]);

        $response = $this->deleteJson(route('items.adjusts.destroy', $itemAdjust));

        $response->assertStatus(204);
        $this->assertSoftDeleted('item_adjusts', ['id' => $itemAdjust->id]);
    });

    it('can list trashed item adjusts', function () {
        ($this->setupInitialInventory)($this->item1->id, $this->warehouse->id, 50);

        $itemAdjust = ItemAdjust::factory()->create([
            'warehouse_id' => $this->warehouse->id,
        ]);
        $itemAdjust->delete();

        $response = $this->getJson(route('items.adjusts.trashed'));

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('can restore trashed item adjust', function () {
        ($this->setupInitialInventory)($this->item1->id, $this->warehouse->id, 50);

        $itemAdjust = ItemAdjust::factory()->create([
            'warehouse_id' => $this->warehouse->id,
        ]);
        $itemAdjust->delete();

        $response = $this->patchJson(route('items.adjusts.restore', $itemAdjust->id));

        $response->assertOk();
        $this->assertDatabaseHas('item_adjusts', [
            'id' => $itemAdjust->id,
            'deleted_at' => null
        ]);
    });

    it('can force delete item adjust', function () {
        ($this->setupInitialInventory)($this->item1->id, $this->warehouse->id, 50);

        $itemAdjust = ItemAdjust::factory()->create([
            'warehouse_id' => $this->warehouse->id,
        ]);
        $itemAdjust->delete();

        $response = $this->deleteJson(route('items.adjusts.force-delete', $itemAdjust->id));

        $response->assertStatus(204);
        $this->assertDatabaseMissing('item_adjusts', ['id' => $itemAdjust->id]);
    });

    it('validates required fields when creating', function () {
        $invalidData = [
            'warehouse_id' => 999, // Non-existent
            'type' => null,
            'items' => [
                [
                    'item_id' => 999, // Non-existent
                    'quantity' => 0, // Zero
                ]
            ]
        ];

        $response = $this->postJson(route('items.adjusts.store'), $invalidData);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'warehouse_id',
                'type',
                'items.0.item_id',
                'items.0.quantity'
            ]);
    });

    it('validates type must be Add or Subtract', function () {
        ($this->setupInitialInventory)($this->item1->id, $this->warehouse->id, 50);

        $invalidData = ($this->getBaseItemAdjustData)([
            'type' => 'Invalid',
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'quantity' => 5,
                ]
            ]
        ]);

        $response = $this->postJson(route('items.adjusts.store'), $invalidData);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['type']);
    });

    it('can filter item adjusts by warehouse', function () {
        $otherWarehouse = Warehouse::factory()->create(['name' => 'Other Warehouse']);

        ItemAdjust::factory()->create([
            'warehouse_id' => $this->warehouse->id,
        ]);
        ItemAdjust::factory()->create([
            'warehouse_id' => $otherWarehouse->id,
        ]);

        $response = $this->getJson(route('items.adjusts.index', ['warehouse_id' => $this->warehouse->id]));

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('can filter item adjusts by type', function () {
        ItemAdjust::factory()->add()->create([
            'warehouse_id' => $this->warehouse->id,
        ]);
        ItemAdjust::factory()->subtract()->create([
            'warehouse_id' => $this->warehouse->id,
        ]);

        $response = $this->getJson(route('items.adjusts.index', ['type' => 'Add']));

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('sets created_by and updated_by fields automatically', function () {
        ($this->setupInitialInventory)($this->item1->id, $this->warehouse->id, 50);

        $itemAdjust = ItemAdjust::factory()->create([
            'warehouse_id' => $this->warehouse->id,
        ]);

        expect($itemAdjust->created_by)->toBe($this->user->id);
        expect($itemAdjust->updated_by)->toBe($this->user->id);

        $itemAdjust->update(['note' => 'Updated note']);
        expect($itemAdjust->fresh()->updated_by)->toBe($this->user->id);
    });

    it('can paginate item adjusts', function () {
        ItemAdjust::factory()->count(7)->create([
            'warehouse_id' => $this->warehouse->id,
        ]);

        $response = $this->getJson(route('items.adjusts.index', ['per_page' => 3]));

        $response->assertOk();
        $data = $response->json('data');
        $pagination = $response->json('pagination');

        expect($data)->toHaveCount(3);
        expect($pagination['total'])->toBe(7);
        expect($pagination['per_page'])->toBe(3);
        expect($pagination['last_page'])->toBe(3);
    });

    it('can retrieve statistics', function () {
        ItemAdjust::factory()->add()->create(['warehouse_id' => $this->warehouse->id]);
        ItemAdjust::factory()->add()->create(['warehouse_id' => $this->warehouse->id]);
        ItemAdjust::factory()->subtract()->create(['warehouse_id' => $this->warehouse->id]);

        $response = $this->getJson(route('items.adjusts.stats'));

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'total_adjustments',
                    'total_add_adjustments',
                    'total_subtract_adjustments',
                ]
            ]);

        expect($response->json('data.total_adjustments'))->toBe(3);
        expect($response->json('data.total_add_adjustments'))->toBe(2);
        expect($response->json('data.total_subtract_adjustments'))->toBe(1);
    });
});

describe('Item Adjust Authorization Tests', function () {
    it('denies salesman to access item adjusts', function () {
        $salesmanUser = User::factory()->create(['role' => User::ROLE_SALESMAN]);
        $this->actingAs($salesmanUser, 'sanctum');

        $response = $this->getJson(route('items.adjusts.index'));

        $response->assertForbidden();
    });

    it('denies warehouse manager to access item adjusts', function () {
        $warehouseManagerUser = User::factory()->create(['role' => User::ROLE_WAREHOUSE_MANAGER]);
        $this->actingAs($warehouseManagerUser, 'sanctum');

        $response = $this->getJson(route('items.adjusts.index'));

        $response->assertForbidden();
    });

    it('denies salesman to create item adjust', function () {
        ($this->setupInitialInventory)($this->item1->id, $this->warehouse->id, 50);

        $salesmanUser = User::factory()->create(['role' => User::ROLE_SALESMAN]);
        $this->actingAs($salesmanUser, 'sanctum');

        $itemAdjustData = ($this->getBaseItemAdjustData)([
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'quantity' => 5,
                ]
            ]
        ]);

        $response = $this->postJson(route('items.adjusts.store'), $itemAdjustData);

        $response->assertForbidden();
    });

    it('denies warehouse manager to create item adjust', function () {
        ($this->setupInitialInventory)($this->item1->id, $this->warehouse->id, 50);

        $warehouseManagerUser = User::factory()->create(['role' => User::ROLE_WAREHOUSE_MANAGER]);
        $this->actingAs($warehouseManagerUser, 'sanctum');

        $itemAdjustData = ($this->getBaseItemAdjustData)([
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'quantity' => 5,
                ]
            ]
        ]);

        $response = $this->postJson(route('items.adjusts.store'), $itemAdjustData);

        $response->assertForbidden();
    });

    it('allows admin to create item adjust', function () {
        ($this->setupInitialInventory)($this->item1->id, $this->warehouse->id, 50);

        $adminUser = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($adminUser, 'sanctum');

        $itemAdjustData = ($this->getBaseItemAdjustData)([
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'quantity' => 5,
                ]
            ]
        ]);

        $response = $this->postJson(route('items.adjusts.store'), $itemAdjustData);

        $response->assertCreated();
    });

    it('allows super admin to create item adjust', function () {
        ($this->setupInitialInventory)($this->item1->id, $this->warehouse->id, 50);

        $superAdminUser = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $this->actingAs($superAdminUser, 'sanctum');

        $itemAdjustData = ($this->getBaseItemAdjustData)([
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'quantity' => 5,
                ]
            ]
        ]);

        $response = $this->postJson(route('items.adjusts.store'), $itemAdjustData);

        $response->assertCreated();
    });

    it('allows developer to create item adjust', function () {
        ($this->setupInitialInventory)($this->item1->id, $this->warehouse->id, 50);

        $developerUser = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $this->actingAs($developerUser, 'sanctum');

        $itemAdjustData = ($this->getBaseItemAdjustData)([
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'quantity' => 5,
                ]
            ]
        ]);

        $response = $this->postJson(route('items.adjusts.store'), $itemAdjustData);

        $response->assertCreated();
    });

    it('allows admin to update item adjust', function () {
        ($this->setupInitialInventory)($this->item1->id, $this->warehouse->id, 50);

        $adminUser = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($adminUser, 'sanctum');

        $itemAdjust = ItemAdjust::factory()->create([
            'warehouse_id' => $this->warehouse->id,
        ]);

        $updateData = [
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'quantity' => 5,
                ]
            ]
        ];

        $response = $this->putJson(route('items.adjusts.update', $itemAdjust), $updateData);

        $response->assertOk();
    });

    it('denies salesman to update item adjust', function () {
        ($this->setupInitialInventory)($this->item1->id, $this->warehouse->id, 50);

        $salesmanUser = User::factory()->create(['role' => User::ROLE_SALESMAN]);
        $this->actingAs($salesmanUser, 'sanctum');

        $itemAdjust = ItemAdjust::factory()->create([
            'warehouse_id' => $this->warehouse->id,
        ]);

        $updateData = [
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'quantity' => 5,
                ]
            ]
        ];

        $response = $this->putJson(route('items.adjusts.update', $itemAdjust), $updateData);

        $response->assertForbidden();
    });

    it('allows admin to delete item adjust', function () {
        ($this->setupInitialInventory)($this->item1->id, $this->warehouse->id, 50);

        $adminUser = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($adminUser, 'sanctum');

        $itemAdjust = ItemAdjust::factory()->create([
            'warehouse_id' => $this->warehouse->id,
        ]);

        $response = $this->deleteJson(route('items.adjusts.destroy', $itemAdjust));

        $response->assertStatus(204);
    });

    it('denies salesman to delete item adjust', function () {
        ($this->setupInitialInventory)($this->item1->id, $this->warehouse->id, 50);

        $salesmanUser = User::factory()->create(['role' => User::ROLE_SALESMAN]);
        $this->actingAs($salesmanUser, 'sanctum');

        $itemAdjust = ItemAdjust::factory()->create([
            'warehouse_id' => $this->warehouse->id,
        ]);

        $response = $this->deleteJson(route('items.adjusts.destroy', $itemAdjust));

        $response->assertForbidden();
    });

    it('denies warehouse manager to delete item adjust', function () {
        ($this->setupInitialInventory)($this->item1->id, $this->warehouse->id, 50);

        $warehouseManagerUser = User::factory()->create(['role' => User::ROLE_WAREHOUSE_MANAGER]);
        $this->actingAs($warehouseManagerUser, 'sanctum');

        $itemAdjust = ItemAdjust::factory()->create([
            'warehouse_id' => $this->warehouse->id,
        ]);

        $response = $this->deleteJson(route('items.adjusts.destroy', $itemAdjust));

        $response->assertForbidden();
    });
});
