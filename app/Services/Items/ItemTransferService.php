<?php

namespace App\Services\Items;

use App\Models\Items\ItemTransfer;
use App\Models\Items\ItemTransferItem;
use App\Models\Items\Item;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ItemTransferService
{
    /**
     * Create a new item transfer with items and all related data
     */
    public function createItemTransferWithItems(array $itemTransferData, array $items = []): ItemTransfer
    {
        try {
            return DB::transaction(function () use ($itemTransferData, $items) {
                // Create the item transfer
                $itemTransfer = ItemTransfer::create($itemTransferData);

                // Process item transfer items
                if (!empty($items)) {
                    $this->processItemTransferItems($itemTransfer, $items);
                }

                return $itemTransfer->fresh();
            });
        } catch (\InvalidArgumentException $e) {
            Log::warning('Item transfer creation validation failed', [
                'error' => $e->getMessage(),
                'item_transfer_data' => $itemTransferData,
                'items_count' => count($items),
            ]);

            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to create item transfer with items', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'item_transfer_data' => $itemTransferData,
                'items_count' => count($items),
            ]);

            throw new \RuntimeException(
                "Failed to create item transfer: " . $e->getMessage() .
                ". All changes have been rolled back.",
                0,
                $e
            );
        }
    }

    /**
     * Update item transfer with items
     */
    public function updateItemTransferWithItems(ItemTransfer $itemTransfer, array $itemTransferData, array $items = []): ItemTransfer
    {
        try {
            return DB::transaction(function () use ($itemTransfer, $itemTransferData, $items) {
                // Update item transfer data
                $itemTransfer->update($itemTransferData);

                $this->updateItemTransferItems($itemTransfer, $items);

                return $itemTransfer->fresh();
            });
        } catch (\InvalidArgumentException $e) {
            Log::warning('Item transfer update validation failed', [
                'error' => $e->getMessage(),
                'item_transfer_id' => $itemTransfer->id,
                'item_transfer_code' => $itemTransfer->code ?? 'N/A',
                'items_count' => count($items),
            ]);

            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to update item transfer with items', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'item_transfer_id' => $itemTransfer->id,
                'item_transfer_code' => $itemTransfer->code ?? 'N/A',
                'items_count' => count($items),
            ]);

            throw new \RuntimeException(
                "Failed to update item transfer #{$itemTransfer->id}: " . $e->getMessage() .
                ". All changes have been rolled back.",
                0,
                $e
            );
        }
    }

    /**
     * Process item transfer items for creation
     */
    private function processItemTransferItems(ItemTransfer $itemTransfer, array $items): void
    {
        foreach ($items as $itemData) {
            $itemTransferItem = $this->createItemTransferItem($itemTransfer, $itemData);

            // Process all related updates (update inventory)
            $this->processItemTransferItemRelatedData($itemTransfer, $itemTransferItem);
        }
    }

    /**
     * Process all related data for an item transfer item
     */
    private function processItemTransferItemRelatedData(ItemTransfer $itemTransfer, ItemTransferItem $itemTransferItem, bool $isUpdate = false, ?float $oldQuantity = null): void
    {
        // Update inventory (subtract from source warehouse, add to destination warehouse)
        $this->updateInventory($itemTransfer, $itemTransferItem, $isUpdate, $oldQuantity);
    }

    /**
     * Update item transfer items individually
     */
    private function updateItemTransferItems(ItemTransfer $itemTransfer, array $items): void
    {
        $processedItemIds = [];

        foreach ($items as $itemData) {
            if (isset($itemData['id'])) {
                // Update existing item
                $itemTransferItem = ItemTransferItem::where('id', $itemData['id'])
                    ->where('item_transfer_id', $itemTransfer->id)
                    ->first();

                if ($itemTransferItem) {
                    $oldQuantity = $itemTransferItem->quantity;

                    // Update the item
                    $quantity = $itemData['quantity'];

                    $itemTransferItem->update([
                        'quantity' => $quantity,
                        'note' => $itemData['note'] ?? $itemTransferItem->note,
                    ]);

                    // Update related data if quantity changed
                    if ((float)$oldQuantity != (float)$quantity) {
                        $this->processItemTransferItemRelatedData($itemTransfer, $itemTransferItem, true, $oldQuantity);
                    }

                    $processedItemIds[] = $itemTransferItem->id;
                }
            } else {
                // Create new item
                $itemTransferItem = $this->createItemTransferItem($itemTransfer, $itemData);
                $this->processItemTransferItemRelatedData($itemTransfer, $itemTransferItem);
                $processedItemIds[] = $itemTransferItem->id;
            }
        }

        // Handle removed items - restore inventory
        $removedItems = $itemTransfer->itemTransferItems()
            ->whereNotIn('id', $processedItemIds)
            ->get();

        foreach ($removedItems as $removedItem) {
            $this->handleItemTransferItemDeletion($itemTransfer, $removedItem);
        }

        // Delete the removed items from database
        $itemTransfer->itemTransferItems()
            ->whereNotIn('id', $processedItemIds)
            ->delete();
    }

    /**
     * Create a single item transfer item
     */
    private function createItemTransferItem(ItemTransfer $itemTransfer, array $itemData): ItemTransferItem
    {
        $preparedData = $this->prepareItemTransferItemData($itemTransfer, $itemData);
        return ItemTransferItem::create($preparedData);
    }

    /**
     * Prepare item transfer item data
     */
    private function prepareItemTransferItemData(ItemTransfer $itemTransfer, array $itemData): array
    {
        $item = Item::findOrFail($itemData['item_id']);

        return [
            'item_transfer_id' => $itemTransfer->id,
            'item_id' => $itemData['item_id'],
            'item_code' => $item->code,
            'quantity' => $itemData['quantity'],
            'note' => $itemData['note'] ?? null,
        ];
    }

    /**
     * Update inventory for item transfer item (subtract from source, add to destination)
     */
    private function updateInventory(ItemTransfer $itemTransfer, ItemTransferItem $itemTransferItem, bool $isUpdate = false, ?float $oldQuantity = null): void
    {
        if ($isUpdate && $oldQuantity !== null) {
            // For updates, adjust inventory by the difference
            $quantityDifference = $itemTransferItem->quantity - $oldQuantity;
            if ($quantityDifference != 0) {
                // Subtract difference from source warehouse
                InventoryService::subtract(
                    $itemTransferItem->item_id,
                    $itemTransfer->from_warehouse_id,
                    $quantityDifference
                );

                // Add difference to destination warehouse
                InventoryService::add(
                    $itemTransferItem->item_id,
                    $itemTransfer->to_warehouse_id,
                    $quantityDifference
                );
            }
        } else {
            // Subtract from source warehouse
            InventoryService::subtract(
                $itemTransferItem->item_id,
                $itemTransfer->from_warehouse_id,
                $itemTransferItem->quantity
            );

            // Add to destination warehouse
            InventoryService::add(
                $itemTransferItem->item_id,
                $itemTransfer->to_warehouse_id,
                $itemTransferItem->quantity
            );
        }
    }

    /**
     * Handle item transfer item deletion - restore inventory to source warehouse
     */
    private function handleItemTransferItemDeletion(ItemTransfer $itemTransfer, ItemTransferItem $itemTransferItem): void
    {
        // Add back to source warehouse (reverse the subtraction)
        InventoryService::add(
            $itemTransferItem->item_id,
            $itemTransfer->from_warehouse_id,
            $itemTransferItem->quantity
        );

        // Subtract from destination warehouse (reverse the addition)
        InventoryService::subtract(
            $itemTransferItem->item_id,
            $itemTransfer->to_warehouse_id,
            $itemTransferItem->quantity
        );
    }
}
