<?php

namespace App\Http\Controllers\Api\Reports\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Inventory\Inventory;
use App\Models\Items\Item;
use App\Models\Setups\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WarehouseReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->fixAllInventory($request);
        $perPage = $request->get('per_page', 15);
        $search = $request->get('search');
        $warehouseId = $request->get('warehouse_id');
        $itemCategoryId = $request->get('item_category_id');
        $itemGroupId = $request->get('item_group_id');
        $itemBrandId = $request->get('item_brand_id');
        $supplierId = $request->get('supplier_id');
        $stockStatus = $request->get('stock_status'); // in_stock, out_of_stock, low_stock

        // Get all active warehouses for column headers
        $warehouses = Warehouse::active()->orderBy('name')->get(['id', 'name']);

        // Build items query
        $query = Item::query()
            ->with(['itemUnit:id,name,short_name', 'itemPrice:id,item_id,price_usd', 'inventories.warehouse:id,name'])
            ->select('items.id', 'items.code', 'items.short_name', 'items.description', 'items.item_unit_id', 'items.low_quantity_alert');

        // Apply search filter
        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('short_name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        // Apply category filter
        if ($itemCategoryId) {
            $query->where('item_category_id', $itemCategoryId);
        }

        // Apply group filter
        if ($itemGroupId) {
            $query->where('item_group_id', $itemGroupId);
        }

        // Apply brand filter
        if ($itemBrandId) {
            $query->where('item_brand_id', $itemBrandId);
        }

        // Apply supplier filter
        if ($supplierId) {
            $query->where('supplier_id', $supplierId);
        }

        // Apply warehouse filter - only show items that have inventory in the specified warehouse
        if ($warehouseId) {
            $query->whereHas('inventories', function ($q) use ($warehouseId) {
                $q->where('warehouse_id', $warehouseId);
            });
        }

        // Apply stock status filter
        if ($stockStatus) {
            switch ($stockStatus) {
                case 'in_stock':
                    $query->whereHas('inventories', function ($q) {
                        $q->where('quantity', '>', 0);
                    });
                    break;
                case 'out_of_stock':
                    $query->where(function ($q) {
                        $q->whereDoesntHave('inventories')
                            ->orWhereHas('inventories', function ($subQ) {
                                $subQ->havingRaw('SUM(quantity) <= 0');
                            });
                    });
                    break;
                case 'low_stock':
                    $query->lowStock();
                    break;
            }
        }

        // Order by item code
        $query->orderBy('items.code');

        // Paginate results
        $items = $query->paginate($perPage);

        // Transform data
        $transformedData = $items->through(function ($item) use ($warehouses, $warehouseId) {
            // Build warehouse quantities
            $warehouseQuantities = [];
            $totalQuantity = 0;

            // If filtering by specific warehouse, only show that warehouse
            $warehousesToShow = $warehouseId
                ? $warehouses->where('id', $warehouseId)
                : $warehouses;

            foreach ($warehousesToShow as $warehouse) {
                $inventory = $item->inventories->firstWhere('warehouse_id', $warehouse->id);
                $quantity = $inventory ? (float) $inventory->quantity : 0;
                $warehouseQuantities[] = [
                    'warehouse_id' => $warehouse->id,
                    'warehouse_name' => $warehouse->name,
                    'quantity' => $quantity,
                ];
                $totalQuantity += $quantity;
            }

            return [
                'id' => $item->id,
                'code' => $item->code,
                'short_name' => $item->short_name,
                'description' => $item->description,
                'unit' => $item->itemUnit ? [
                    'id' => $item->itemUnit->id,
                    'name' => $item->itemUnit->name,
                    'short_name' => $item->itemUnit->short_name,
                ] : null,
                'price_usd' => $item->itemPrice ? (float) $item->itemPrice->price_usd : null,
                'low_quantity_alert' => $item->low_quantity_alert ? (float) $item->low_quantity_alert : null,
                'total_quantity' => $totalQuantity,
                'warehouse_quantities' => $warehouseQuantities,
                'stock_status' => $this->getStockStatus($totalQuantity, $item->low_quantity_alert),
            ];
        });

        // Calculate summary stats
        $stats = $this->calculateStats($warehouseId);

        return ApiResponse::paginated(
            'Warehouse inventory report retrieved successfully',
            $transformedData,
            null,
            [
                'warehouses' => $warehouses->map(fn($w) => ['id' => $w->id, 'name' => $w->name]),
                'summary' => $stats,
            ]
        );
    }

    public function show(Request $request, Item $item): JsonResponse
    {
        $warehouseId = $request->get('warehouse_id');

        // Get all active warehouses
        $warehouses = Warehouse::active()->orderBy('name')->get(['id', 'name']);

        // Load item relationships
        $item->load(['itemUnit:id,name,short_name', 'itemPrice:id,item_id,price_usd', 'inventories.warehouse:id,name']);

        // Build warehouse quantities
        $warehouseQuantities = [];
        $totalQuantity = 0;

        // If filtering by specific warehouse, only show that warehouse
        $warehousesToShow = $warehouseId
            ? $warehouses->where('id', $warehouseId)
            : $warehouses;

        foreach ($warehousesToShow as $warehouse) {
            $inventory = $item->inventories->firstWhere('warehouse_id', $warehouse->id);
            $quantity = $inventory ? (float) $inventory->quantity : 0;
            $warehouseQuantities[] = [
                'warehouse_id' => $warehouse->id,
                'warehouse_name' => $warehouse->name,
                'quantity' => $quantity,
            ];
            $totalQuantity += $quantity;
        }

        $data = [
            'id' => $item->id,
            'code' => $item->code,
            'short_name' => $item->short_name,
            'description' => $item->description,
            'unit' => $item->itemUnit ? [
                'id' => $item->itemUnit->id,
                'name' => $item->itemUnit->name,
                'short_name' => $item->itemUnit->short_name,
            ] : null,
            'price_usd' => $item->itemPrice ? (float) $item->itemPrice->price_usd : null,
            'low_quantity_alert' => $item->low_quantity_alert ? (float) $item->low_quantity_alert : null,
            'total_quantity' => $totalQuantity,
            'warehouse_quantities' => $warehouseQuantities,
            'stock_status' => $this->getStockStatus($totalQuantity, $item->low_quantity_alert),
            'warehouses' => $warehouses->map(fn($w) => ['id' => $w->id, 'name' => $w->name]),
        ];

        return ApiResponse::show('Item warehouse inventory retrieved successfully', $data);
    }

    private function getStockStatus(float $quantity, ?float $lowAlert): string
    {
        if ($quantity < 0) {
            return 'negative';
        }
        if ($quantity == 0) {
            return 'out_of_stock';
        }
        if ($lowAlert && $quantity <= $lowAlert) {
            return 'low_stock';
        }
        return 'in_stock';
    }

    private function calculateStats(?int $warehouseId): array
    {
        $baseQuery = Item::query();

        if ($warehouseId) {
            return [
                'total_items' => (clone $baseQuery)->whereHas('inventories', fn($q) => $q->where('warehouse_id', $warehouseId))->count(),
                'in_stock_items' => (clone $baseQuery)->whereHas('inventories', fn($q) => $q->where('warehouse_id', $warehouseId)->where('quantity', '>', 0))->count(),
                'out_of_stock_items' => (clone $baseQuery)->whereHas('inventories', fn($q) => $q->where('warehouse_id', $warehouseId)->where('quantity', '<=', 0))->count(),
                'low_stock_items' => (clone $baseQuery)
                    ->whereNotNull('low_quantity_alert')
                    ->whereHas('inventories', fn($q) => $q->where('warehouse_id', $warehouseId)
                        ->whereColumn('quantity', '<=', 'items.low_quantity_alert')
                        ->where('quantity', '>', 0)
                    )->count(),
            ];
        }

        return [
            'total_items' => Item::count(),
            'in_stock_items' => Item::whereHas('inventories', fn($q) => $q->where('quantity', '>', 0))->count(),
            'out_of_stock_items' => Item::whereDoesntHave('inventories')
                ->orWhereHas('inventories', function ($q) {
                    $q->selectRaw('item_id, SUM(quantity) as total')
                        ->groupBy('item_id')
                        ->havingRaw('SUM(quantity) <= 0');
                })->count(),
            'low_stock_items' => Item::lowStock()->count(),
        ];
    }

    /**
     * Fix inventory for all items based on transaction history
     */
    public function fixAllInventory(Request $request): JsonResponse
    {
        $dryRun = $request->boolean('dry_run', false);
        $warehouseId = $request->get('warehouse_id');

        try {
            Log::info('=== INVENTORY FIX STARTED (ALL ITEMS) ===', [
                'dry_run' => $dryRun,
                'warehouse_id' => $warehouseId,
                'initiated_by' => auth()->user()?->name ?? 'System',
                'initiated_at' => now()->toDateTimeString(),
            ]);

            $fixResults = $this->performInventoryFix(null, $warehouseId, $dryRun);

            Log::info('=== INVENTORY FIX COMPLETED ===', [
                'total_checked' => $fixResults['total_checked'],
                'total_fixed' => $fixResults['total_fixed'],
                'total_errors' => count($fixResults['errors']),
            ]);

            $message = $dryRun
                ? 'Inventory fix dry run completed (no changes made)'
                : 'Inventory fix completed successfully';

            return ApiResponse::send($message, 200, $fixResults);
        } catch (\Exception $e) {
            Log::error('INVENTORY FIX FAILED', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);

            return ApiResponse::send('Inventory fix failed: ' . $e->getMessage(), 500, [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Fix inventory for a specific item based on transaction history
     */
    public function fixItemInventory(Request $request, Item $item): JsonResponse
    {
        $dryRun = $request->boolean('dry_run', false);
        $warehouseId = $request->get('warehouse_id');

        Log::channel('daily')->info('=== INVENTORY FIX STARTED (SINGLE ITEM) ===', [
            'item_id' => $item->id,
            'item_code' => $item->code,
            'dry_run' => $dryRun,
            'warehouse_id' => $warehouseId,
            'initiated_by' => auth()->user()?->name ?? 'System',
            'initiated_at' => now()->toDateTimeString(),
        ]);

        $fixResults = $this->performInventoryFix($item->id, $warehouseId, $dryRun);

        Log::channel('daily')->info('=== INVENTORY FIX COMPLETED (SINGLE ITEM) ===', [
            'item_id' => $item->id,
            'item_code' => $item->code,
            'total_fixed' => $fixResults['total_fixed'],
        ]);

        $message = $dryRun
            ? 'Inventory fix dry run completed for item (no changes made)'
            : 'Inventory fix completed successfully for item';

        return ApiResponse::send($message, 200, $fixResults);
    }

    /**
     * Perform the actual inventory fix
     */
    private function performInventoryFix(?int $itemId, ?int $warehouseId, bool $dryRun): array
    {
        $results = [
            'total_checked' => 0,
            'total_fixed' => 0,
            'fixes' => [],
            'errors' => [],
        ];

        // Build query to get expected quantities from item_movements_view
        $query = DB::table('item_movements_view')
            ->select(
                'item_id',
                'warehouse_id',
                DB::raw('SUM(credit) - SUM(debit) as expected_quantity')
            )
            ->groupBy('item_id', 'warehouse_id');

        if ($itemId) {
            $query->where('item_id', $itemId);
        }

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        $expectedQuantities = $query->get();

        // Get all current inventory records
        $currentInventoryQuery = Inventory::query();
        if ($itemId) {
            $currentInventoryQuery->where('item_id', $itemId);
        }
        if ($warehouseId) {
            $currentInventoryQuery->where('warehouse_id', $warehouseId);
        }
        $currentInventories = $currentInventoryQuery->get()->keyBy(function ($inv) {
            return $inv->item_id . '_' . $inv->warehouse_id;
        });

        // Get item codes for logging
        $itemIds = $expectedQuantities->pluck('item_id')->unique()->toArray();
        $items = Item::whereIn('id', $itemIds)->pluck('code', 'id');

        // Get warehouse names for logging
        $warehouseIds = $expectedQuantities->pluck('warehouse_id')->unique()->toArray();
        $warehouses = Warehouse::whereIn('id', $warehouseIds)->pluck('name', 'id');

        // Track which item-warehouse combinations we've processed from movements
        $processedKeys = [];

        foreach ($expectedQuantities as $expected) {
            $results['total_checked']++;
            $key = $expected->item_id . '_' . $expected->warehouse_id;
            $processedKeys[] = $key;

            $currentInventory = $currentInventories->get($key);
            $currentQty = $currentInventory ? (float) $currentInventory->quantity : 0;
            $expectedQty = (float) $expected->expected_quantity;

            // Check if there's a discrepancy
            if (abs($currentQty - $expectedQty) > 0.0001) {
                $itemCode = $items[$expected->item_id] ?? 'Unknown';
                $warehouseName = $warehouses[$expected->warehouse_id] ?? 'Unknown';

                $fixRecord = [
                    'item_id' => $expected->item_id,
                    'item_code' => $itemCode,
                    'warehouse_id' => $expected->warehouse_id,
                    'warehouse_name' => $warehouseName,
                    'from_quantity' => $currentQty,
                    'to_quantity' => $expectedQty,
                    'difference' => $expectedQty - $currentQty,
                ];

                if (!$dryRun) {
                    try {
                        if ($currentInventory) {
                            // Update existing inventory
                            $currentInventory->update(['quantity' => $expectedQty]);
                        } else {
                            // Create new inventory record
                            Inventory::create([
                                'item_id' => $expected->item_id,
                                'warehouse_id' => $expected->warehouse_id,
                                'quantity' => $expectedQty,
                            ]);
                        }

                        Log::channel('daily')->info('INVENTORY FIXED', $fixRecord);
                        $fixRecord['status'] = 'fixed';
                    } catch (\Exception $e) {
                        Log::channel('daily')->error('INVENTORY FIX ERROR', [
                            ...$fixRecord,
                            'error' => $e->getMessage(),
                        ]);
                        $fixRecord['status'] = 'error';
                        $fixRecord['error'] = $e->getMessage();
                        $results['errors'][] = $fixRecord;
                        continue;
                    }
                } else {
                    $fixRecord['status'] = 'dry_run';
                    Log::channel('daily')->info('INVENTORY FIX (DRY RUN)', $fixRecord);
                }

                $results['fixes'][] = $fixRecord;
                $results['total_fixed']++;
            }
        }

        // Check for inventory records that exist but have no movements (orphaned records)
        // These should be set to 0 if they have quantity > 0
        foreach ($currentInventories as $key => $inventory) {
            if (!in_array($key, $processedKeys) && $inventory->quantity != 0) {
                $results['total_checked']++;

                $itemCode = $items[$inventory->item_id] ?? Item::find($inventory->item_id)?->code ?? 'Unknown';
                $warehouseName = $warehouses[$inventory->warehouse_id] ?? Warehouse::find($inventory->warehouse_id)?->name ?? 'Unknown';

                $fixRecord = [
                    'item_id' => $inventory->item_id,
                    'item_code' => $itemCode,
                    'warehouse_id' => $inventory->warehouse_id,
                    'warehouse_name' => $warehouseName,
                    'from_quantity' => (float) $inventory->quantity,
                    'to_quantity' => 0,
                    'difference' => -(float) $inventory->quantity,
                    'note' => 'No transactions found - reset to 0',
                ];

                if (!$dryRun) {
                    try {
                        $inventory->update(['quantity' => 0]);
                        Log::channel('daily')->info('INVENTORY FIXED (ORPHANED)', $fixRecord);
                        $fixRecord['status'] = 'fixed';
                    } catch (\Exception $e) {
                        Log::channel('daily')->error('INVENTORY FIX ERROR (ORPHANED)', [
                            ...$fixRecord,
                            'error' => $e->getMessage(),
                        ]);
                        $fixRecord['status'] = 'error';
                        $fixRecord['error'] = $e->getMessage();
                        $results['errors'][] = $fixRecord;
                        continue;
                    }
                } else {
                    $fixRecord['status'] = 'dry_run';
                    Log::channel('daily')->info('INVENTORY FIX (DRY RUN - ORPHANED)', $fixRecord);
                }

                $results['fixes'][] = $fixRecord;
                $results['total_fixed']++;
            }
        }

        return $results;
    }

    /**
     * Preview inventory discrepancies without fixing
     */
    public function previewDiscrepancies(Request $request): JsonResponse
    {
        $warehouseId = $request->get('warehouse_id');
        $itemId = $request->get('item_id');

        // Get expected quantities from movements view
        $query = DB::table('item_movements_view')
            ->select(
                'item_id',
                'warehouse_id',
                DB::raw('SUM(credit) as total_credit'),
                DB::raw('SUM(debit) as total_debit'),
                DB::raw('SUM(credit) - SUM(debit) as expected_quantity')
            )
            ->groupBy('item_id', 'warehouse_id');

        if ($itemId) {
            $query->where('item_id', $itemId);
        }

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        $expectedQuantities = $query->get();

        // Get all current inventory records
        $currentInventoryQuery = Inventory::query();
        if ($itemId) {
            $currentInventoryQuery->where('item_id', $itemId);
        }
        if ($warehouseId) {
            $currentInventoryQuery->where('warehouse_id', $warehouseId);
        }
        $currentInventories = $currentInventoryQuery->get()->keyBy(function ($inv) {
            return $inv->item_id . '_' . $inv->warehouse_id;
        });

        // Get item codes and warehouse names
        $itemIds = $expectedQuantities->pluck('item_id')->unique()->toArray();
        $items = Item::whereIn('id', $itemIds)->get()->keyBy('id');

        $warehouseIds = $expectedQuantities->pluck('warehouse_id')->unique()->toArray();
        $warehouses = Warehouse::whereIn('id', $warehouseIds)->pluck('name', 'id');

        $discrepancies = [];
        $processedKeys = [];

        foreach ($expectedQuantities as $expected) {
            $key = $expected->item_id . '_' . $expected->warehouse_id;
            $processedKeys[] = $key;

            $currentInventory = $currentInventories->get($key);
            $currentQty = $currentInventory ? (float) $currentInventory->quantity : 0;
            $expectedQty = (float) $expected->expected_quantity;

            if (abs($currentQty - $expectedQty) > 0.0001) {
                $item = $items[$expected->item_id] ?? null;
                $discrepancies[] = [
                    'item_id' => $expected->item_id,
                    'item_code' => $item?->code ?? 'Unknown',
                    'item_name' => $item?->short_name ?? 'Unknown',
                    'warehouse_id' => $expected->warehouse_id,
                    'warehouse_name' => $warehouses[$expected->warehouse_id] ?? 'Unknown',
                    'current_quantity' => $currentQty,
                    'expected_quantity' => $expectedQty,
                    'difference' => $expectedQty - $currentQty,
                    'total_credit' => (float) $expected->total_credit,
                    'total_debit' => (float) $expected->total_debit,
                ];
            }
        }

        // Check for orphaned inventory records
        foreach ($currentInventories as $key => $inventory) {
            if (!in_array($key, $processedKeys) && $inventory->quantity != 0) {
                $item = Item::find($inventory->item_id);
                $discrepancies[] = [
                    'item_id' => $inventory->item_id,
                    'item_code' => $item?->code ?? 'Unknown',
                    'item_name' => $item?->short_name ?? 'Unknown',
                    'warehouse_id' => $inventory->warehouse_id,
                    'warehouse_name' => $warehouses[$inventory->warehouse_id] ?? Warehouse::find($inventory->warehouse_id)?->name ?? 'Unknown',
                    'current_quantity' => (float) $inventory->quantity,
                    'expected_quantity' => 0,
                    'difference' => -(float) $inventory->quantity,
                    'total_credit' => 0,
                    'total_debit' => 0,
                    'note' => 'No transactions found',
                ];
            }
        }

        return ApiResponse::send('Inventory discrepancies retrieved', 200, [
            'total_discrepancies' => count($discrepancies),
            'discrepancies' => $discrepancies,
        ]);
    }
}
