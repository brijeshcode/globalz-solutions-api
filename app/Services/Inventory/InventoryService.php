<?php

namespace App\Services\Inventory;

use App\Models\Inventory\Inventory;
use App\Models\Items\Item;
use App\Models\Setups\Warehouse;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class InventoryService
{
    /**
     * Core inventory update method - handles all operations
     */
    public static function updateQuantity(int $itemId, int $warehouseId, int $newQuantity, string $operation = 'set', ?string $reason = null): Inventory
    {
        self::validateItemAndWarehouse($itemId, $warehouseId);

        return DB::transaction(function () use ($itemId, $warehouseId, $newQuantity, $operation, $reason) {
            $inventory = self::findOrCreateInventory($itemId, $warehouseId);
            $oldQuantity = $inventory->quantity;

            switch ($operation) {
                case 'add':
                    if ($newQuantity <= 0) throw new InvalidArgumentException('Add quantity must be greater than 0');
                    $inventory->quantity += $newQuantity;
                    break;
                    
                case 'subtract':
                    if ($newQuantity <= 0) throw new InvalidArgumentException('Subtract quantity must be greater than 0');
                    if ($inventory->quantity < $newQuantity) {
                        throw new InvalidArgumentException("Insufficient inventory. Available: {$inventory->quantity}, Required: {$newQuantity}");
                    }
                    $inventory->quantity -= $newQuantity;
                    break;
                    
                case 'set':
                    if ($newQuantity < 0) throw new InvalidArgumentException('Set quantity cannot be negative');
                    $inventory->quantity = $newQuantity;
                    break;
                    
                case 'adjust':
                    $inventory->quantity += $newQuantity; // Can be positive or negative
                    if ($inventory->quantity < 0) {
                        throw new InvalidArgumentException("Adjustment would result in negative inventory");
                    }
                    break;
                    
                default:
                    throw new InvalidArgumentException("Invalid operation: {$operation}");
            }

            $inventory->save();
            return $inventory;
        });
    }

    /**
     * Simplified operation methods (backward compatible)
     */
    public static function add(int $itemId, int $warehouseId, int $quantity, ?string $reason = null): Inventory
    {
        return self::updateQuantity($itemId, $warehouseId, $quantity, 'add', $reason);
    }

    public static function subtract(int $itemId, int $warehouseId, int $quantity, ?string $reason = null): Inventory
    {
        return self::updateQuantity($itemId, $warehouseId, $quantity, 'subtract', $reason);
    }

    public static function set(int $itemId, int $warehouseId, int $quantity, ?string $reason = null): Inventory
    {
        return self::updateQuantity($itemId, $warehouseId, $quantity, 'set', $reason);
    }

    public static function adjust(int $itemId, int $warehouseId, int $quantityDifference, ?string $reason = null): Inventory
    {
        return self::updateQuantity($itemId, $warehouseId, $quantityDifference, 'adjust', $reason);
    }

    /**
     * Get current quantity for item in warehouse
     */
    public static function getQuantity(int $itemId, int $warehouseId): int
    {
        $inventory = Inventory::byWarehouseAndItem($warehouseId, $itemId)->first();
        return $inventory ? $inventory->quantity : 0;
    }

    /**
     * Check if inventory record exists
     */
    public static function exists(int $itemId, int $warehouseId): bool
    {
        return Inventory::byWarehouseAndItem($warehouseId, $itemId)->exists();
    }

    /**
     * Check if enough stock is available
     */
    public static function hasStock(int $itemId, int $warehouseId, int $requiredQuantity = 1): bool
    {
        return self::getQuantity($itemId, $warehouseId) >= $requiredQuantity;
    }

    /**
     * Process multiple inventory operations in batch
     */
    public static function batchUpdate(array $items, string $operation = 'add', ?string $reason = null): array
    {
        return DB::transaction(function () use ($items, $operation, $reason) {
            $results = [];

            foreach ($items as $item) {
                $itemId = $item['item_id'];
                $warehouseId = $item['warehouse_id'];
                $quantity = $item['quantity'];
                $itemReason = $item['reason'] ?? $reason;

                $results[] = self::updateQuantity($itemId, $warehouseId, $quantity, $operation, $itemReason);
            }

            return $results;
        });
    }

    /**
     * Simplified batch methods (backward compatible)
     */
    public static function addBatch(array $items, ?string $reason = null): array
    {
        return self::batchUpdate($items, 'add', $reason);
    }

    public static function subtractBatch(array $items, ?string $reason = null): array
    {
        return self::batchUpdate($items, 'subtract', $reason);
    }

    /**
     * Transfer inventory between warehouses
     */
    public static function transfer(int $itemId, int $fromWarehouseId, int $toWarehouseId, int $quantity, ?string $reason = null): array
    {
        if ($quantity <= 0) {
            throw new InvalidArgumentException('Quantity must be greater than 0');
        }

        self::validateItemAndWarehouse($itemId, $fromWarehouseId);
        self::validateItemAndWarehouse($itemId, $toWarehouseId);

        return DB::transaction(function () use ($itemId, $fromWarehouseId, $toWarehouseId, $quantity, $reason) {
            $transferReason = $reason ?? "Transfer from warehouse {$fromWarehouseId} to {$toWarehouseId}";
            
            $fromInventory = self::subtract($itemId, $fromWarehouseId, $quantity, $transferReason);
            $toInventory = self::add($itemId, $toWarehouseId, $quantity, $transferReason);

            return [
                'from' => $fromInventory,
                'to' => $toInventory
            ];
        });
    }

    /**
     * Execute operations within a transaction
     */
    public static function transaction(callable $callback)
    {
        return DB::transaction($callback);
    }

    /**
     * Get inventory record for item in warehouse
     */
    public static function getInventory(int $itemId, int $warehouseId): ?Inventory
    {
        return Inventory::byWarehouseAndItem($warehouseId, $itemId)->first();
    }

    /**
     * Get all inventory records for an item across all warehouses
     */
    public static function getItemInventoryAcrossWarehouses(int $itemId): array
    {
        return Inventory::where('item_id', $itemId)
            ->with('warehouse')
            ->get()
            ->toArray();
    }

    /**
     * Get total quantity for an item across all warehouses
     */
    public static function getTotalQuantityAcrossWarehouses(int $itemId): int
    {
        return Inventory::where('item_id', $itemId)->sum('quantity');
    }

    /**
     * Find existing inventory or create new record
     */
    protected static function findOrCreateInventory(int $itemId, int $warehouseId): Inventory
    {
        $inventory = self::getInventory($warehouseId, $itemId);
        
        if (!$inventory) {
            $inventory = Inventory::create([
                'warehouse_id' => $warehouseId,
                'item_id' => $itemId,
                'quantity' => 0,
            ]);
        }

        return $inventory;
    }

    /**
     * Validate that item and warehouse exist
     */
    protected static function validateItemAndWarehouse(int $itemId, int $warehouseId): void
    {
        if (!Item::find($itemId)) {
            throw new InvalidArgumentException("Item with ID {$itemId} not found");
        }

        if (!Warehouse::find($warehouseId)) {
            throw new InvalidArgumentException("Warehouse with ID {$warehouseId} not found");
        }
    }

    /**
     * Get inventory movement history for an item
     */
    public static function getMovementHistory(int $itemId, ?int $warehouseId = null, ?int $limit = 100): array
    {
        $movements = [];
        
        // Get purchase movements
        $purchaseQuery = DB::table('purchases as p')
            ->join('purchase_items as pi', 'p.id', '=', 'pi.purchase_id')
            ->where('pi.item_id', $itemId)
            ->select([
                DB::raw("'purchase' as source_type"),
                'p.id as source_id',
                'pi.item_id',
                'p.warehouse_id',
                'pi.quantity as quantity_change',
                DB::raw("'add' as movement_type"),
                'p.date as movement_date',
                DB::raw("CONCAT('Purchase #', p.id) as description"),
                'p.created_at'
            ]);
            
        if ($warehouseId) {
            $purchaseQuery->where('p.warehouse_id', $warehouseId);
        }
        
        $movements = array_merge($movements, $purchaseQuery->get()->toArray());
        
        // Add other movement types (sales, transfers, adjustments) here as needed
        // Following the same pattern...
        
        // Sort by date descending
        usort($movements, function($a, $b) {
            return strtotime($b->movement_date) - strtotime($a->movement_date);
        });
        
        return $limit ? array_slice($movements, 0, $limit) : $movements;
    }

    /**
     * Get current inventory balance for reporting
     */
    public static function getInventoryBalance(?int $warehouseId = null): array
    {
        $query = DB::table('inventory as i')
            ->join('items as it', 'i.item_id', '=', 'it.id')
            ->join('warehouses as w', 'i.warehouse_id', '=', 'w.id')
            ->select([
                'i.item_id',
                'it.name as item_name',
                'it.code as item_code',
                'i.warehouse_id',
                'w.name as warehouse_name',
                'i.quantity',
                'i.updated_at as last_updated'
            ])
            ->where('i.quantity', '>', 0);
            
        if ($warehouseId) {
            $query->where('i.warehouse_id', $warehouseId);
        }
        
        return $query->orderBy('it.name')->get()->toArray();
    }
}