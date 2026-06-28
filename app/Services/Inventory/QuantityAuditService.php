<?php

namespace App\Services\Inventory;

use App\Models\Inventory\Inventory;
use App\Models\Items\Item;
use App\Models\Setups\Warehouse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class QuantityAuditService
{
    public static function auditQuantities(?int $warehouseId = null): array
    {
        return self::scan(null, $warehouseId);
    }

    public static function auditSingleItemQuantity(int $itemId, ?int $warehouseId = null): array
    {
        return self::scan($itemId, $warehouseId);
    }

    public static function auditAndFixQuantities(?int $warehouseId = null, bool $dryRun = false): array
    {
        return self::fix(null, $warehouseId, $dryRun);
    }

    public static function auditAndFixSingleItemQuantity(int $itemId, ?int $warehouseId = null, bool $dryRun = false): array
    {
        return self::fix($itemId, $warehouseId, $dryRun);
    }

    // -------------------------------------------------------------------------

    private static function scan(?int $itemId, ?int $warehouseId): array
    {
        $query = DB::table('item_movements_view')
            ->select(
                'item_id',
                'warehouse_id',
                DB::raw('SUM(credit) as total_credit'),
                DB::raw('SUM(debit) as total_debit'),
                DB::raw('SUM(credit) - SUM(debit) as expected_quantity')
            )
            ->groupBy('item_id', 'warehouse_id');

        if ($itemId) $query->where('item_id', $itemId);
        if ($warehouseId) $query->where('warehouse_id', $warehouseId);

        $expectedQuantities = $query->get();

        $currentInventoryQuery = Inventory::query();
        if ($itemId) $currentInventoryQuery->where('item_id', $itemId);
        if ($warehouseId) $currentInventoryQuery->where('warehouse_id', $warehouseId);
        $currentInventories = $currentInventoryQuery->get()
            ->keyBy(fn($inv) => $inv->item_id . '_' . $inv->warehouse_id);

        $itemIds      = $expectedQuantities->pluck('item_id')->unique()->toArray();
        $items        = Item::whereIn('id', $itemIds)->get()->keyBy('id');
        $warehouseIds = $expectedQuantities->pluck('warehouse_id')->unique()->toArray();
        $warehouses   = Warehouse::whereIn('id', $warehouseIds)->pluck('name', 'id');

        $discrepancies = [];
        $processedKeys = [];

        foreach ($expectedQuantities as $expected) {
            $key = $expected->item_id . '_' . $expected->warehouse_id;
            $processedKeys[] = $key;

            $currentInventory = $currentInventories->get($key);
            $currentQty       = $currentInventory ? (float) $currentInventory->quantity : 0;
            $expectedQty      = (float) $expected->expected_quantity;

            if (abs($currentQty - $expectedQty) > 0.0001) {
                $item            = $items[$expected->item_id] ?? null;
                $discrepancies[] = [
                    'item_id'           => $expected->item_id,
                    'item_code'         => $item?->code ?? 'Unknown',
                    'item_name'         => $item?->short_name ?? 'Unknown',
                    'warehouse_id'      => $expected->warehouse_id,
                    'warehouse_name'    => $warehouses[$expected->warehouse_id] ?? 'Unknown',
                    'current_quantity'  => $currentQty,
                    'expected_quantity' => $expectedQty,
                    'difference'        => $expectedQty - $currentQty,
                    'total_credit'      => (float) $expected->total_credit,
                    'total_debit'       => (float) $expected->total_debit,
                ];
            }
        }

        foreach ($currentInventories as $key => $inventory) {
            if (!in_array($key, $processedKeys) && $inventory->quantity != 0) {
                $item            = Item::find($inventory->item_id);
                $discrepancies[] = [
                    'item_id'           => $inventory->item_id,
                    'item_code'         => $item?->code ?? 'Unknown',
                    'item_name'         => $item?->short_name ?? 'Unknown',
                    'warehouse_id'      => $inventory->warehouse_id,
                    'warehouse_name'    => $warehouses[$inventory->warehouse_id] ?? Warehouse::find($inventory->warehouse_id)?->name ?? 'Unknown',
                    'current_quantity'  => (float) $inventory->quantity,
                    'expected_quantity' => 0,
                    'difference'        => -(float) $inventory->quantity,
                    'total_credit'      => 0,
                    'total_debit'       => 0,
                    'note'              => 'No transactions found',
                ];
            }
        }

        return [
            'total_discrepancies' => count($discrepancies),
            'discrepancies'       => $discrepancies,
        ];
    }

    private static function fix(?int $itemId, ?int $warehouseId, bool $dryRun): array
    {
        $results = [
            'total_checked' => 0,
            'total_fixed'   => 0,
            'fixes'         => [],
            'errors'        => [],
        ];

        $query = DB::table('item_movements_view')
            ->select('item_id', 'warehouse_id', DB::raw('SUM(credit) - SUM(debit) as expected_quantity'))
            ->groupBy('item_id', 'warehouse_id');

        if ($itemId) $query->where('item_id', $itemId);
        if ($warehouseId) $query->where('warehouse_id', $warehouseId);

        $expectedQuantities = $query->get();

        $currentInventoryQuery = Inventory::query();
        if ($itemId) $currentInventoryQuery->where('item_id', $itemId);
        if ($warehouseId) $currentInventoryQuery->where('warehouse_id', $warehouseId);
        $currentInventories = $currentInventoryQuery->get()
            ->keyBy(fn($inv) => $inv->item_id . '_' . $inv->warehouse_id);

        $itemIds      = $expectedQuantities->pluck('item_id')->unique()->toArray();
        $items        = Item::whereIn('id', $itemIds)->pluck('code', 'id');
        $warehouseIds = $expectedQuantities->pluck('warehouse_id')->unique()->toArray();
        $warehouses   = Warehouse::whereIn('id', $warehouseIds)->pluck('name', 'id');

        $processedKeys = [];

        foreach ($expectedQuantities as $expected) {
            $results['total_checked']++;
            $key             = $expected->item_id . '_' . $expected->warehouse_id;
            $processedKeys[] = $key;

            $currentInventory = $currentInventories->get($key);
            $currentQty       = $currentInventory ? (float) $currentInventory->quantity : 0;
            $expectedQty      = (float) $expected->expected_quantity;

            if (abs($currentQty - $expectedQty) > 0.0001) {
                $fixRecord = [
                    'item_id'        => $expected->item_id,
                    'item_code'      => $items[$expected->item_id] ?? 'Unknown',
                    'warehouse_id'   => $expected->warehouse_id,
                    'warehouse_name' => $warehouses[$expected->warehouse_id] ?? 'Unknown',
                    'from_quantity'  => $currentQty,
                    'to_quantity'    => $expectedQty,
                    'difference'     => $expectedQty - $currentQty,
                ];

                if (!$dryRun) {
                    try {
                        if ($currentInventory) {
                            $currentInventory->update(['quantity' => $expectedQty]);
                        } else {
                            Inventory::create(['item_id' => $expected->item_id, 'warehouse_id' => $expected->warehouse_id, 'quantity' => $expectedQty]);
                        }
                        Log::channel('daily')->info('INVENTORY FIXED', $fixRecord);
                        $fixRecord['status'] = 'fixed';
                    } catch (\Exception $e) {
                        Log::channel('daily')->error('INVENTORY FIX ERROR', [...$fixRecord, 'error' => $e->getMessage()]);
                        $fixRecord['status'] = 'error';
                        $fixRecord['error']  = $e->getMessage();
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

        foreach ($currentInventories as $key => $inventory) {
            if (!in_array($key, $processedKeys) && $inventory->quantity != 0) {
                $results['total_checked']++;

                $fixRecord = [
                    'item_id'        => $inventory->item_id,
                    'item_code'      => $items[$inventory->item_id] ?? Item::find($inventory->item_id)?->code ?? 'Unknown',
                    'warehouse_id'   => $inventory->warehouse_id,
                    'warehouse_name' => $warehouses[$inventory->warehouse_id] ?? Warehouse::find($inventory->warehouse_id)?->name ?? 'Unknown',
                    'from_quantity'  => (float) $inventory->quantity,
                    'to_quantity'    => 0,
                    'difference'     => -(float) $inventory->quantity,
                    'note'           => 'No transactions found - reset to 0',
                ];

                if (!$dryRun) {
                    try {
                        $inventory->update(['quantity' => 0]);
                        Log::channel('daily')->info('INVENTORY FIXED (ORPHANED)', $fixRecord);
                        $fixRecord['status'] = 'fixed';
                    } catch (\Exception $e) {
                        Log::channel('daily')->error('INVENTORY FIX ERROR (ORPHANED)', [...$fixRecord, 'error' => $e->getMessage()]);
                        $fixRecord['status'] = 'error';
                        $fixRecord['error']  = $e->getMessage();
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
}
