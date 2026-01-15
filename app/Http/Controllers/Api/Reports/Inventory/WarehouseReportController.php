<?php

namespace App\Http\Controllers\Api\Reports\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Items\Item;
use App\Models\Setups\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WarehouseReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
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
}
