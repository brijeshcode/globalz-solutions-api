<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Items\ItemTransfer;
use App\Models\Items\ItemTransferItem;
use App\Models\Items\Item;
use App\Models\Setups\Warehouse;
use App\Models\Inventory\Inventory;
use App\Models\User;
use App\Models\Setting;

uses(RefreshDatabase::class)->group('api', 'items', 'item-transfers');

beforeEach(function () {
    // Create item transfer code counter setting
    Setting::create([
        'group_name' => 'item_transfers',
        'key_name' => 'code_counter',
        'value' => '1000',
        'data_type' => 'number',
        'description' => 'Item transfer code counter starting from 1000'
    ]);

    // Create warehouses for testing
    $this->fromWarehouse = Warehouse::factory()->create(['name' => 'Source Warehouse']);
    $this->toWarehouse = Warehouse::factory()->create(['name' => 'Destination Warehouse']);

    // Create test items
    $this->item1 = Item::factory()->create([
        'code' => 'ITEM001',
        'short_name' => 'Test Item 1',
    ]);
    $this->item2 = Item::factory()->create([
        'code' => 'ITEM002',
        'short_name' => 'Test Item 2',
    ]);

    // Helper method for base item transfer data
    $this->getBaseItemTransferData = function ($overrides = []) {
        return array_merge([
            'date' => '2025-01-15',
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->toWarehouse->id,
        ], $overrides);
    };

    // Helper method to create item transfer via API
    $this->createItemTransferViaApi = function ($overrides = []) {
        $itemTransferData = ($this->getBaseItemTransferData)($overrides);

        // Ensure items are provided if not in overrides
        if (!isset($itemTransferData['items'])) {
            $itemTransferData['items'] = [
                [
                    'item_id' => $this->item1->id,
                    'quantity' => 10,
                ]
            ];
        }

        $response = $this->postJson(route('items.transfers.store'), $itemTransferData);
        $response->assertCreated();

        $itemTransferId = $response->json('data.id');
        return ItemTransfer::find($itemTransferId);
    };

    // Helper to setup initial inventory
    $this->setupInitialInventory = function (int $itemId, int $warehouseId, float $quantity) {
        Inventory::updateOrCreate(
            ['warehouse_id' => $warehouseId, 'item_id' => $itemId],
            ['quantity' => $quantity]
        );
    };
});

describe('Item Transfers API', function () {
    beforeEach(function () {
        $this->user = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($this->user, 'sanctum');
    });

    it('can list item transfers', function () {
        ItemTransfer::factory()->count(3)->create([
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->toWarehouse->id,
        ]);

        $response = $this->getJson(route('items.transfers.index'));

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    '*' => [
                        'id',
                        'code',
                        'date',
                        'from_warehouse',
                        'to_warehouse',
                    ]
                ],
                'pagination'
            ]);

        expect($response->json('data'))->toHaveCount(3);
    });

    it('can create item transfer with items and updates inventory', function () {
        // Setup initial inventory
        ($this->setupInitialInventory)($this->item1->id, $this->fromWarehouse->id, 100);
        ($this->setupInitialInventory)($this->item2->id, $this->fromWarehouse->id, 200);

        $itemTransferData = ($this->getBaseItemTransferData)([
            'note' => 'Test transfer with multiple items',
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'quantity' => 10,
                    'note' => 'First transferred item'
                ],
                [
                    'item_id' => $this->item2->id,
                    'quantity' => 15,
                    'note' => 'Second transferred item'
                ]
            ]
        ]);

        $response = $this->postJson(route('items.transfers.store'), $itemTransferData);

        $response->assertCreated()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'code',
                    'items',
                    'from_warehouse',
                    'to_warehouse'
                ]
            ]);

        // Verify item transfer was created
        $this->assertDatabaseHas('item_transfers', [
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->toWarehouse->id,
        ]);

        // Verify item transfer items were created
        $itemTransfer = ItemTransfer::latest()->first();
        expect($itemTransfer->itemTransferItems)->toHaveCount(2);

        // Verify inventory was updated in source warehouse (reduced)
        $inventoryFrom1 = Inventory::where('warehouse_id', $this->fromWarehouse->id)
            ->where('item_id', $this->item1->id)
            ->first();
        expect($inventoryFrom1->quantity)->toBe('90.0000'); // 100 - 10

        $inventoryFrom2 = Inventory::where('warehouse_id', $this->fromWarehouse->id)
            ->where('item_id', $this->item2->id)
            ->first();
        expect($inventoryFrom2->quantity)->toBe('185.0000'); // 200 - 15

        // Verify inventory was added in destination warehouse
        $inventoryTo1 = Inventory::where('warehouse_id', $this->toWarehouse->id)
            ->where('item_id', $this->item1->id)
            ->first();
        expect($inventoryTo1->quantity)->toBe('10.0000');

        $inventoryTo2 = Inventory::where('warehouse_id', $this->toWarehouse->id)
            ->where('item_id', $this->item2->id)
            ->first();
        expect($inventoryTo2->quantity)->toBe('15.0000');
    });

    it('can show item transfer with all relationships', function () {
        ($this->setupInitialInventory)($this->item1->id, $this->fromWarehouse->id, 50);

        $itemTransfer = ($this->createItemTransferViaApi)();

        $response = $this->getJson(route('items.transfers.show', $itemTransfer));

        $response->assertOk()
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'code',
                    'from_warehouse' => ['id', 'name'],
                    'to_warehouse' => ['id', 'name'],
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

    it('can update item transfer with item sync', function () {
        // Setup initial inventory
        ($this->setupInitialInventory)($this->item1->id, $this->fromWarehouse->id, 100);
        ($this->setupInitialInventory)($this->item2->id, $this->fromWarehouse->id, 200);

        // Create initial item transfer via API
        $itemTransfer = ($this->createItemTransferViaApi)([
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'quantity' => 5,
                ]
            ]
        ]);

        $existingItem = $itemTransfer->itemTransferItems->first();

        // Update item transfer data
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

        $response = $this->putJson(route('items.transfers.update', $itemTransfer), $updateData);

        $response->assertOk();

        // Verify item transfer was updated
        $itemTransfer->refresh();

        // Verify items were synced
        expect($itemTransfer->itemTransferItems)->toHaveCount(2);

        // Verify existing item was updated
        $existingItem->refresh();
        expect($existingItem->quantity)->toBe('8.0000');

        // Verify inventory was updated correctly in source warehouse
        // Initial: 100, first transfer: -5, update to 8: additional -3 = 92
        $inventoryFrom = Inventory::where('warehouse_id', $this->fromWarehouse->id)
            ->where('item_id', $this->item1->id)
            ->first();
        expect($inventoryFrom->quantity)->toBe('92.0000');

        // Verify inventory was updated correctly in destination warehouse
        // Initial: 0, first transfer: +5, update to 8: additional +3 = 8
        $inventoryTo = Inventory::where('warehouse_id', $this->toWarehouse->id)
            ->where('item_id', $this->item1->id)
            ->first();
        expect($inventoryTo->quantity)->toBe('8.0000');
    });

    it('auto-generates item transfer codes', function () {
        ($this->setupInitialInventory)($this->item1->id, $this->fromWarehouse->id, 50);

        $itemTransferData = ($this->getBaseItemTransferData)([
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'quantity' => 5,
                ]
            ]
        ]);

        $response = $this->postJson(route('items.transfers.store'), $itemTransferData);

        $response->assertCreated();

        $itemTransfer = ItemTransfer::where('from_warehouse_id', $this->fromWarehouse->id)->first();
        expect($itemTransfer->code)->not()->toBeNull();
        expect($itemTransfer->prefix)->toBe('TRAN');
    });

    it('can soft delete item transfer', function () {
        ($this->setupInitialInventory)($this->item1->id, $this->fromWarehouse->id, 50);

        $itemTransfer = ItemTransfer::factory()->create([
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->toWarehouse->id,
        ]);

        $response = $this->deleteJson(route('items.transfers.destroy', $itemTransfer));

        $response->assertStatus(204);
        $this->assertSoftDeleted('item_transfers', ['id' => $itemTransfer->id]);
    });

    it('can list trashed item transfers', function () {
        ($this->setupInitialInventory)($this->item1->id, $this->fromWarehouse->id, 50);

        $itemTransfer = ItemTransfer::factory()->create([
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->toWarehouse->id,
        ]);
        $itemTransfer->delete();

        $response = $this->getJson(route('items.transfers.trashed'));

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('can restore trashed item transfer', function () {
        ($this->setupInitialInventory)($this->item1->id, $this->fromWarehouse->id, 50);

        $itemTransfer = ItemTransfer::factory()->create([
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->toWarehouse->id,
        ]);
        $itemTransfer->delete();

        $response = $this->patchJson(route('items.transfers.restore', $itemTransfer->id));

        $response->assertOk();
        $this->assertDatabaseHas('item_transfers', [
            'id' => $itemTransfer->id,
            'deleted_at' => null
        ]);
    });

    it('can force delete item transfer', function () {
        ($this->setupInitialInventory)($this->item1->id, $this->fromWarehouse->id, 50);

        $itemTransfer = ItemTransfer::factory()->create([
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->toWarehouse->id,
        ]);
        $itemTransfer->delete();

        $response = $this->deleteJson(route('items.transfers.force-delete', $itemTransfer->id));

        $response->assertStatus(204);
        $this->assertDatabaseMissing('item_transfers', ['id' => $itemTransfer->id]);
    });

    it('validates required fields when creating', function () {
        $invalidData = [
            'from_warehouse_id' => 999, // Non-existent
            'to_warehouse_id' => null,
            'items' => [
                [
                    'item_id' => 999, // Non-existent
                    'quantity' => 0, // Zero
                ]
            ]
        ];

        $response = $this->postJson(route('items.transfers.store'), $invalidData);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors([
                'from_warehouse_id',
                'to_warehouse_id',
                'items.0.item_id',
                'items.0.quantity'
            ]);
    });

    it('validates warehouses are different', function () {
        ($this->setupInitialInventory)($this->item1->id, $this->fromWarehouse->id, 50);

        $invalidData = ($this->getBaseItemTransferData)([
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->fromWarehouse->id, // Same as from
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'quantity' => 5,
                ]
            ]
        ]);

        $response = $this->postJson(route('items.transfers.store'), $invalidData);

        $response->assertUnprocessable()
            ->assertJsonValidationErrors(['to_warehouse_id']);
    });

    it('can filter item transfers by source warehouse', function () {
        $otherWarehouse = Warehouse::factory()->create(['name' => 'Other Warehouse']);

        ItemTransfer::factory()->create([
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->toWarehouse->id,
        ]);
        ItemTransfer::factory()->create([
            'from_warehouse_id' => $otherWarehouse->id,
            'to_warehouse_id' => $this->toWarehouse->id,
        ]);

        $response = $this->getJson(route('items.transfers.index', ['from_warehouse_id' => $this->fromWarehouse->id]));

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

    it('can filter item transfers by destination warehouse', function () {
        $otherWarehouse = Warehouse::factory()->create(['name' => 'Other Warehouse']);

        ItemTransfer::factory()->create([
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->toWarehouse->id,
        ]);
        ItemTransfer::factory()->create([
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $otherWarehouse->id,
        ]);

        $response = $this->getJson(route('items.transfers.index', ['to_warehouse_id' => $this->toWarehouse->id]));

        $response->assertOk()
            ->assertJsonCount(1, 'data');
    });

     
    it('sets created_by and updated_by fields automatically', function () {
        ($this->setupInitialInventory)($this->item1->id, $this->fromWarehouse->id, 50);

        $itemTransfer = ItemTransfer::factory()->create([
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->toWarehouse->id,
        ]);

        expect($itemTransfer->created_by)->toBe($this->user->id);
        expect($itemTransfer->updated_by)->toBe($this->user->id);

        $itemTransfer->update(['note' => 'Updated note']);
        expect($itemTransfer->fresh()->updated_by)->toBe($this->user->id);
    });

    it('can paginate item transfers', function () {
        ItemTransfer::factory()->count(7)->create([
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->toWarehouse->id,
        ]);

        $response = $this->getJson(route('items.transfers.index', ['per_page' => 3]));

        $response->assertOk();
        $data = $response->json('data');
        $pagination = $response->json('pagination');

        expect($data)->toHaveCount(3);
        expect($pagination['total'])->toBe(7);
        expect($pagination['per_page'])->toBe(3);
        expect($pagination['last_page'])->toBe(3);
    });

     
});

describe('Item Transfer Authorization Tests', function () {
    it('denies salesman to create item transfer', function () {
        ($this->setupInitialInventory)($this->item1->id, $this->fromWarehouse->id, 50);

        $salesmanUser = User::factory()->create(['role' => User::ROLE_SALESMAN]);
        $this->actingAs($salesmanUser, 'sanctum');

        $itemTransferData = ($this->getBaseItemTransferData)([
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'quantity' => 5,
                ]
            ]
        ]);

        $response = $this->postJson(route('items.transfers.store'), $itemTransferData);

        $response->assertForbidden();
    });

    it('denies warehouse manager to create item transfer', function () {
        ($this->setupInitialInventory)($this->item1->id, $this->fromWarehouse->id, 50);

        $warehouseManagerUser = User::factory()->create(['role' => User::ROLE_WAREHOUSE_MANAGER]);
        $this->actingAs($warehouseManagerUser, 'sanctum');

        $itemTransferData = ($this->getBaseItemTransferData)([
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'quantity' => 5,
                ]
            ]
        ]);

        $response = $this->postJson(route('items.transfers.store'), $itemTransferData);

        $response->assertForbidden();
    });

    it('allows admin to create item transfer', function () {
        ($this->setupInitialInventory)($this->item1->id, $this->fromWarehouse->id, 50);

        $adminUser = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($adminUser, 'sanctum');

        $itemTransferData = ($this->getBaseItemTransferData)([
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'quantity' => 5,
                ]
            ]
        ]);

        $response = $this->postJson(route('items.transfers.store'), $itemTransferData);

        $response->assertCreated();
    });

    it('allows super admin to create item transfer', function () {
        ($this->setupInitialInventory)($this->item1->id, $this->fromWarehouse->id, 50);

        $superAdminUser = User::factory()->create(['role' => User::ROLE_SUPER_ADMIN]);
        $this->actingAs($superAdminUser, 'sanctum');

        $itemTransferData = ($this->getBaseItemTransferData)([
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'quantity' => 5,
                ]
            ]
        ]);

        $response = $this->postJson(route('items.transfers.store'), $itemTransferData);

        $response->assertCreated();
    });

    it('allows developer to create item transfer', function () {
        ($this->setupInitialInventory)($this->item1->id, $this->fromWarehouse->id, 50);

        $developerUser = User::factory()->create(['role' => User::ROLE_DEVELOPER]);
        $this->actingAs($developerUser, 'sanctum');

        $itemTransferData = ($this->getBaseItemTransferData)([
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'quantity' => 5,
                ]
            ]
        ]);

        $response = $this->postJson(route('items.transfers.store'), $itemTransferData);

        $response->assertCreated();
    });

    it('allows admin to update item transfer', function () {
        ($this->setupInitialInventory)($this->item1->id, $this->fromWarehouse->id, 50);

        $adminUser = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($adminUser, 'sanctum');

        $itemTransfer = ItemTransfer::factory()->create([
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->toWarehouse->id,
        ]);

        $updateData = [
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'quantity' => 5,
                ]
            ]
        ];

        $response = $this->putJson(route('items.transfers.update', $itemTransfer), $updateData);

        $response->assertOk();
    });

    it('denies salesman to update item transfer', function () {
        ($this->setupInitialInventory)($this->item1->id, $this->fromWarehouse->id, 50);

        $salesmanUser = User::factory()->create(['role' => User::ROLE_SALESMAN]);
        $this->actingAs($salesmanUser, 'sanctum');

        $itemTransfer = ItemTransfer::factory()->create([
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->toWarehouse->id,
        ]);

        $updateData = [ 
            'items' => [
                [
                    'item_id' => $this->item1->id,
                    'quantity' => 5,
                ]
            ]
        ];

        $response = $this->putJson(route('items.transfers.update', $itemTransfer), $updateData);

        $response->assertForbidden();
    });

    it('allows admin to delete item transfer', function () {
        ($this->setupInitialInventory)($this->item1->id, $this->fromWarehouse->id, 50);

        $adminUser = User::factory()->create(['role' => User::ROLE_ADMIN]);
        $this->actingAs($adminUser, 'sanctum');

        $itemTransfer = ItemTransfer::factory()->create([
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->toWarehouse->id,
        ]);

        $response = $this->deleteJson(route('items.transfers.destroy', $itemTransfer));

        $response->assertStatus(204);
    });

    it('denies salesman to delete item transfer', function () {
        ($this->setupInitialInventory)($this->item1->id, $this->fromWarehouse->id, 50);

        $salesmanUser = User::factory()->create(['role' => User::ROLE_SALESMAN]);
        $this->actingAs($salesmanUser, 'sanctum');

        $itemTransfer = ItemTransfer::factory()->create([
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->toWarehouse->id,
        ]);

        $response = $this->deleteJson(route('items.transfers.destroy', $itemTransfer));

        $response->assertForbidden();
    });

    it('denies warehouse manager to delete item transfer', function () {
        ($this->setupInitialInventory)($this->item1->id, $this->fromWarehouse->id, 50);

        $warehouseManagerUser = User::factory()->create(['role' => User::ROLE_WAREHOUSE_MANAGER]);
        $this->actingAs($warehouseManagerUser, 'sanctum');

        $itemTransfer = ItemTransfer::factory()->create([
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->toWarehouse->id,
        ]);

        $response = $this->deleteJson(route('items.transfers.destroy', $itemTransfer));

        $response->assertForbidden();
    });
});
