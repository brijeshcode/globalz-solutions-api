<?php

namespace App\Http\Controllers\Api\Reports\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Responses\ApiResponse;
use App\Models\Items\Item;
use App\Models\Items\PriceList;
use App\Models\Setups\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Mpdf\Mpdf;

class WarehouseReportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = $request->get('per_page', 50);
        $search = $request->get('search');
        $warehouseId = $request->get('warehouse_id');
        $itemCategoryId = $request->get('item_category_id');
        $itemGroupId = $request->get('item_group_id');
        $itemBrandId = $request->get('item_brand_id');
        $supplierId = $request->get('supplier_id');
        $stockStatus = $request->get('stock_status'); // in_stock, out_of_stock, low_stock
        $itemTypeId = $request->get('item_type_id');
        $itemFamilyId = $request->get('item_family_id');
        $status = $request->get('status'); // active, inactive

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

        // Apply type filter
        if ($itemTypeId) {
            $query->where('item_type_id', $itemTypeId);
        }

        // Apply family filter
        if ($itemFamilyId) {
            $query->where('item_family_id', $itemFamilyId);
        }

        // Apply status filter
        if ($status) {
            $query->where('is_active', $status === 'active');
        }

        // Apply warehouse filter - only show items that have inventory in the specified warehouse
        if ($warehouseId) {
            $query->whereHas('inventories', function ($q) use ($warehouseId) {
                $q->where('warehouse_id', $warehouseId);
            });
        }

        // Apply stock status filter (scoped to selected warehouse if provided)
        if ($stockStatus) {
            switch ($stockStatus) {
                case 'in_stock':
                    $query->whereHas('inventories', function ($q) use ($warehouseId) {
                        if ($warehouseId) {
                            $q->where('warehouse_id', $warehouseId);
                        }
                        $q->where('quantity', '>', 0);
                    });
                    break;
                case 'out_of_stock':
                    if ($warehouseId) {
                        $query->where(function ($q) use ($warehouseId) {
                            $q->whereDoesntHave('inventories', fn($subQ) => $subQ->where('warehouse_id', $warehouseId))
                                ->orWhereHas('inventories', fn($subQ) => $subQ->where('warehouse_id', $warehouseId)->where('quantity', '<=', 0));
                        });
                    } else {
                        $query->where(function ($q) {
                            $q->whereDoesntHave('inventories')
                                ->orWhereHas('inventories', function ($subQ) {
                                    $subQ->havingRaw('SUM(quantity) <= 0');
                                });
                        });
                    }
                    break;
                case 'low_stock':
                    if ($warehouseId) {
                        $query->whereNotNull('low_quantity_alert')
                            ->whereHas('inventories', function ($q) use ($warehouseId) {
                                $q->where('warehouse_id', $warehouseId)
                                    ->whereColumn('quantity', '<=', 'items.low_quantity_alert')
                                    ->where('quantity', '>', 0);
                            });
                    } else {
                        $query->lowStock();
                    }
                    break;
                case 'negative':
                    $query->whereHas('inventories', function ($q) use ($warehouseId) {
                        if ($warehouseId) {
                            $q->where('warehouse_id', $warehouseId);
                        }
                        $q->where('quantity', '<', 0);
                    });
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

            $priceUsd = $item->itemPrice ? (float) $item->itemPrice->price_usd : 0;

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
                'price_usd' => $priceUsd ?: null,
                'low_quantity_alert' => $item->low_quantity_alert ? (float) $item->low_quantity_alert : null,
                'total_quantity' => $totalQuantity,
                'value' => $totalQuantity * $priceUsd,
                'warehouse_quantities' => $warehouseQuantities,
                'stock_status' => $this->getStockStatus($totalQuantity, $item->low_quantity_alert),
            ];
        });

        // Calculate summary stats
        $stats = $this->calculateStats($warehouseId, $search, $itemCategoryId, $itemGroupId, $itemBrandId, $supplierId, $stockStatus, $itemTypeId, $itemFamilyId, $status);

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

    public function priceListReport(Request $request): JsonResponse
    {
        $perPage     = $request->get('per_page', 50);
        $search      = $request->get('search');
        $warehouseId = $request->get('warehouse_id');

        $warehouses       = Warehouse::active()->orderBy('name')->get(['id', 'name']);
        $defaultPriceList = PriceList::getDefaultInv();

        $query = Item::query()
            ->with(['inventories.warehouse:id,name'])
            ->where('items.is_active', true)
            ->select('items.id', 'items.code', 'items.short_name', 'items.description')
            ->leftJoin('price_list_items', function ($join) use ($defaultPriceList) {
                $join->on('price_list_items.item_id', '=', 'items.id')
                    ->where('price_list_items.price_list_id', $defaultPriceList?->id)
                    ->whereNull('price_list_items.deleted_at');
            })
            ->addSelect('price_list_items.sell_price');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('items.code', 'like', "%{$search}%")
                    ->orWhere('items.short_name', 'like', "%{$search}%")
                    ->orWhere('items.description', 'like', "%{$search}%")
                    ->orWhere('items.barcode', 'like', "%{$search}%");
            });
        }

        if ($warehouseId) {
            $query->whereHas('inventories', fn($q) => $q->where('warehouse_id', $warehouseId));
        }

        $query->orderBy('items.code');

        $items = $query->paginate($perPage);

        $transformedData = $items->through(function ($item) use ($warehouses, $warehouseId) {
            $warehouseQuantities = [];
            $totalQuantity       = 0;

            $warehousesToShow = $warehouseId
                ? $warehouses->where('id', $warehouseId)
                : $warehouses;

            foreach ($warehousesToShow as $warehouse) {
                $inventory = $item->inventories->firstWhere('warehouse_id', $warehouse->id);
                $quantity  = $inventory ? (float) $inventory->quantity : 0;
                $warehouseQuantities[] = [
                    'warehouse_id'   => $warehouse->id,
                    'warehouse_name' => $warehouse->name,
                    'quantity'       => $quantity,
                ];
                $totalQuantity += $quantity;
            }

            return [
                'id'                  => $item->id,
                'code'                => $item->code,
                'short_name'          => $item->short_name,
                'description'         => $item->description,
                'sell_price'          => $item->sell_price !== null ? (float) $item->sell_price : null,
                'total_quantity'      => $totalQuantity,
                'warehouse_quantities' => $warehouseQuantities,
            ];
        });

        return ApiResponse::paginated(
            'Price list inventory report retrieved successfully',
            $transformedData,
            null,
            [
                'warehouses'    => $warehouses->map(fn($w) => ['id' => $w->id, 'name' => $w->name]),
                'price_list_id' => $defaultPriceList?->id,
                'price_list'    => $defaultPriceList ? ['id' => $defaultPriceList->id, 'code' => $defaultPriceList->code, 'description' => $defaultPriceList->description] : null,
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

    public function priceListExport(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $search      = $request->get('search');
        $warehouseId = $request->get('warehouse_id');

        $warehouses       = Warehouse::active()->orderBy('name')->get(['id', 'name']);
        $defaultPriceList = PriceList::getDefaultInv();

        $query = Item::query()
            ->with(['inventories'])
            ->where('items.is_active', true)
            ->select('items.id', 'items.code', 'items.short_name', 'items.description')
            ->leftJoin('price_list_items', function ($join) use ($defaultPriceList) {
                $join->on('price_list_items.item_id', '=', 'items.id')
                    ->where('price_list_items.price_list_id', $defaultPriceList?->id)
                    ->whereNull('price_list_items.deleted_at');
            })
            ->addSelect('price_list_items.sell_price');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('items.code', 'like', "%{$search}%")
                    ->orWhere('items.short_name', 'like', "%{$search}%")
                    ->orWhere('items.description', 'like', "%{$search}%")
                    ->orWhere('items.barcode', 'like', "%{$search}%");
            });
        }

        if ($warehouseId) {
            $query->whereHas('inventories', fn($q) => $q->where('warehouse_id', $warehouseId));
        }

        $query->orderBy('items.code');
        $items = $query->get();

        $warehousesToShow = $warehouseId ? $warehouses->where('id', $warehouseId) : $warehouses;

        $rows = $items->map(function ($item) use ($warehousesToShow) {
            $warehouseQuantities = [];
            $totalQuantity       = 0;

            foreach ($warehousesToShow as $warehouse) {
                $inventory = $item->inventories->firstWhere('warehouse_id', $warehouse->id);
                $quantity  = $inventory ? (float) $inventory->quantity : 0;
                $warehouseQuantities[] = [
                    'warehouse_id'   => $warehouse->id,
                    'warehouse_name' => $warehouse->name,
                    'quantity'       => $quantity,
                ];
                $totalQuantity += $quantity;
            }

            return [
                'code'                 => $item->code,
                'short_name'           => $item->short_name,
                'description'          => $item->description,
                'sell_price'           => $item->sell_price !== null ? (float) $item->sell_price : null,
                'total_quantity'       => $totalQuantity,
                'warehouse_quantities' => $warehouseQuantities,
            ];
        });

        $html = view('pdfs.inventory-price-list-report', [
            'rows'             => $rows,
            'warehouses'       => $warehousesToShow->values(),
            'defaultPriceList' => $defaultPriceList,
            'generatedAt'      => now()->format('Y-m-d H:i'),
        ])->render();

        $mpdf = new Mpdf([
            'mode'          => 'utf-8',
            'format'        => 'A4-L',
            'margin_left'   => 8,
            'margin_right'  => 8,
            'margin_top'    => 10,
            'margin_bottom' => 15,
            'margin_header' => 5,
            'margin_footer' => 5,
        ]);

        $mpdf->SetHTMLFooter('
            <table width="100%" style="font-size: 8pt; border-top: 1px solid #000; padding-top: 4px;">
                <tr>
                    <td style="text-align: left;">Price List Inventory Report</td>
                    <td style="text-align: center;">Page {PAGENO} of {nbpg}</td>
                    <td style="text-align: right;">' . now()->format('Y-m-d') . '</td>
                </tr>
            </table>');

        $mpdf->WriteHTML($html);

        $filename = 'price-list-inventory-' . now()->format('Y-m-d') . '.pdf';

        return response()->streamDownload(function () use ($mpdf) {
            echo $mpdf->Output('', 'S');
        }, $filename, ['Content-Type' => 'application/pdf']);
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

    private function calculateStats(
        ?int $warehouseId,
        ?string $search = null,
        ?int $itemCategoryId = null,
        ?int $itemGroupId = null,
        ?int $itemBrandId = null,
        ?int $supplierId = null,
        ?string $stockStatus = null,
        ?int $itemTypeId = null,
        ?int $itemFamilyId = null,
        ?string $status = null
    ): array {
        $baseQuery = Item::query();

        // Apply the same filters as the main query
        if ($search) {
            $baseQuery->where(function ($q) use ($search) {
                $q->where('code', 'like', "%{$search}%")
                    ->orWhere('short_name', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('barcode', 'like', "%{$search}%");
            });
        }

        if ($itemCategoryId) {
            $baseQuery->where('item_category_id', $itemCategoryId);
        }

        if ($itemGroupId) {
            $baseQuery->where('item_group_id', $itemGroupId);
        }

        if ($itemBrandId) {
            $baseQuery->where('item_brand_id', $itemBrandId);
        }

        if ($supplierId) {
            $baseQuery->where('supplier_id', $supplierId);
        }

        if ($itemTypeId) {
            $baseQuery->where('item_type_id', $itemTypeId);
        }

        if ($itemFamilyId) {
            $baseQuery->where('item_family_id', $itemFamilyId);
        }

        if ($status) {
            $baseQuery->where('is_active', $status === 'active');
        }

        if ($warehouseId) {
            $baseQuery->whereHas('inventories', fn($q) => $q->where('warehouse_id', $warehouseId));
        }

        if ($stockStatus) {
            switch ($stockStatus) {
                case 'in_stock':
                    $baseQuery->whereHas('inventories', function ($q) use ($warehouseId) {
                        if ($warehouseId) {
                            $q->where('warehouse_id', $warehouseId);
                        }
                        $q->where('quantity', '>', 0);
                    });
                    break;
                case 'out_of_stock':
                    if ($warehouseId) {
                        $baseQuery->where(function ($q) use ($warehouseId) {
                            $q->whereDoesntHave('inventories', fn($subQ) => $subQ->where('warehouse_id', $warehouseId))
                                ->orWhereHas('inventories', fn($subQ) => $subQ->where('warehouse_id', $warehouseId)->where('quantity', '<=', 0));
                        });
                    } else {
                        $baseQuery->where(function ($q) {
                            $q->whereDoesntHave('inventories')
                                ->orWhereHas('inventories', function ($subQ) {
                                    $subQ->havingRaw('SUM(quantity) <= 0');
                                });
                        });
                    }
                    break;
                case 'low_stock':
                    if ($warehouseId) {
                        $baseQuery->whereNotNull('low_quantity_alert')
                            ->whereHas('inventories', function ($q) use ($warehouseId) {
                                $q->where('warehouse_id', $warehouseId)
                                    ->whereColumn('quantity', '<=', 'items.low_quantity_alert')
                                    ->where('quantity', '>', 0);
                            });
                    } else {
                        $baseQuery->lowStock();
                    }
                    break;
                case 'negative':
                    $baseQuery->whereHas('inventories', function ($q) use ($warehouseId) {
                        if ($warehouseId) {
                            $q->where('warehouse_id', $warehouseId);
                        }
                        $q->where('quantity', '<', 0);
                    });
                    break;
            }
        }

        // Get filtered item IDs for stock value calculation
        $filteredItemIds = (clone $baseQuery)->pluck('items.id');

        // Calculate total stock value (SUM of quantity * price_usd) for filtered items
        $stockValueQuery = DB::table('inventories')
            ->join('item_prices', 'inventories.item_id', '=', 'item_prices.item_id')
            ->whereIn('inventories.item_id', $filteredItemIds);

        if ($warehouseId) {
            $stockValueQuery->where('inventories.warehouse_id', $warehouseId);
        }

        $totalStockValue = (float) $stockValueQuery->sum(DB::raw('inventories.quantity * item_prices.price_usd'));

        if ($warehouseId) {
            return [
                'total_items' => (clone $baseQuery)->count(),
                'in_stock_items' => (clone $baseQuery)->whereHas('inventories', fn($q) => $q->where('warehouse_id', $warehouseId)->where('quantity', '>', 0))->count(),
                'out_of_stock_items' => (clone $baseQuery)->whereHas('inventories', fn($q) => $q->where('warehouse_id', $warehouseId)->where('quantity', '<=', 0))->count(),
                'low_stock_items' => (clone $baseQuery)
                    ->whereNotNull('low_quantity_alert')
                    ->whereHas('inventories', fn($q) => $q->where('warehouse_id', $warehouseId)
                        ->whereColumn('quantity', '<=', 'items.low_quantity_alert')
                        ->where('quantity', '>', 0)
                    )->count(),
                'total_stock_value' => $totalStockValue,
            ];
        }

        return [
            'total_items' => (clone $baseQuery)->count(),
            'in_stock_items' => (clone $baseQuery)->whereHas('inventories', fn($q) => $q->where('quantity', '>', 0))->count(),
            'out_of_stock_items' => (clone $baseQuery)->where(function ($q) {
                $q->whereDoesntHave('inventories')
                    ->orWhereHas('inventories', function ($subQ) {
                        $subQ->selectRaw('item_id, SUM(quantity) as total')
                            ->groupBy('item_id')
                            ->havingRaw('SUM(quantity) <= 0');
                    });
            })->count(),
            'low_stock_items' => (clone $baseQuery)->lowStock()->count(),
            'total_stock_value' => $totalStockValue,
        ];
    }

    public function fixAllInventory(Request $request): JsonResponse
    {
        $dryRun      = $request->boolean('dry_run', false);
        $warehouseId = $request->filled('warehouse_id') ? (int) $request->get('warehouse_id') : null;

        try {
            $fixResults = QuantityAuditService::auditAndFixQuantities($warehouseId, $dryRun);

            $message = $dryRun
                ? 'Inventory fix dry run completed (no changes made)'
                : 'Inventory fix completed successfully';

            return ApiResponse::send($message, 200, $fixResults);
        } catch (\Exception $e) {
            Log::error('INVENTORY FIX FAILED', ['error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
            return ApiResponse::send('Inventory fix failed: ' . $e->getMessage(), 500, ['error' => $e->getMessage()]);
        }
    }

    public function fixItemInventory(Request $request, Item $item): JsonResponse
    {
        $dryRun      = $request->boolean('dry_run', false);
        $warehouseId = $request->filled('warehouse_id') ? (int) $request->get('warehouse_id') : null;

        $fixResults = QuantityAuditService::auditAndFixSingleItemQuantity($item->id, $warehouseId, $dryRun);

        $message = $dryRun
            ? 'Inventory fix dry run completed for item (no changes made)'
            : 'Inventory fix completed successfully for item';

        return ApiResponse::send($message, 200, $fixResults);
    }

    public function previewDiscrepancies(Request $request): JsonResponse
    {
        $warehouseId = $request->filled('warehouse_id') ? (int) $request->get('warehouse_id') : null;
        $itemId      = $request->filled('item_id') ? (int) $request->get('item_id') : null;

        $result = $itemId
            ? QuantityAuditService::auditSingleItemQuantity($itemId, $warehouseId)
            : QuantityAuditService::auditQuantities($warehouseId);

        return ApiResponse::send('Inventory discrepancies retrieved', 200, $result);
    }
}
