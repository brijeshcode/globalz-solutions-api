<?php

namespace App\Services\Items;

use App\Models\Items\ItemAdjust;
use App\Models\Items\ItemAdjustItem;
use App\Models\Items\Item;
use App\Services\Inventory\InventoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ItemAdjustService
{
    /**
     * Create a new item adjust with items and all related data
     */
    public function createItemAdjustWithItems(array $itemAdjustData, array $items = []): ItemAdjust
    {
        try {
            return DB::transaction(function () use ($itemAdjustData, $items) {
                // Create the item adjust
                $itemAdjust = ItemAdjust::create($itemAdjustData);

                // Process item adjust items
                if (!empty($items)) {
                    $this->processItemAdjustItems($itemAdjust, $items);
                }

                return $itemAdjust->fresh();
            });
        } catch (\InvalidArgumentException $e) {
            Log::warning('Item adjust creation validation failed', [
                'error' => $e->getMessage(),
                'item_adjust_data' => $itemAdjustData,
                'items_count' => count($items),
            ]);

            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to create item adjust with items', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'item_adjust_data' => $itemAdjustData,
                'items_count' => count($items),
            ]);

            throw new \RuntimeException(
                "Failed to create item adjust: " . $e->getMessage() .
                ". All changes have been rolled back.",
                0,
                $e
            );
        }
    }

    /**
     * Update item adjust with items
     */
    public function updateItemAdjustWithItems(ItemAdjust $itemAdjust, array $itemAdjustData, array $items = []): ItemAdjust
    {
        try {
            return DB::transaction(function () use ($itemAdjust, $itemAdjustData, $items) {
                // Update item adjust data
                $itemAdjust->update($itemAdjustData);

                $this->updateItemAdjustItems($itemAdjust, $items);

                return $itemAdjust->fresh();
            });
        } catch (\InvalidArgumentException $e) {
            Log::warning('Item adjust update validation failed', [
                'error' => $e->getMessage(),
                'item_adjust_id' => $itemAdjust->id,
                'item_adjust_code' => $itemAdjust->code ?? 'N/A',
                'items_count' => count($items),
            ]);

            throw $e;
        } catch (\Exception $e) {
            Log::error('Failed to update item adjust with items', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'item_adjust_id' => $itemAdjust->id,
                'item_adjust_code' => $itemAdjust->code ?? 'N/A',
                'items_count' => count($items),
            ]);

            throw new \RuntimeException(
                "Failed to update item adjust #{$itemAdjust->id}: " . $e->getMessage() .
                ". All changes have been rolled back.",
                0,
                $e
            );
        }
    }

    /**
     * Process item adjust items for creation
     */
    private function processItemAdjustItems(ItemAdjust $itemAdjust, array $items): void
    {
        foreach ($items as $itemData) {
            $itemAdjustItem = $this->createItemAdjustItem($itemAdjust, $itemData);

            // Process all related updates (update inventory)
            $this->processItemAdjustItemRelatedData($itemAdjust, $itemAdjustItem);
        }
    }

    /**
     * Process all related data for an item adjust item
     */
    private function processItemAdjustItemRelatedData(ItemAdjust $itemAdjust, ItemAdjustItem $itemAdjustItem, bool $isUpdate = false, ?float $oldQuantity = null): void
    {
        // Update inventory (add or subtract based on type)
        $this->updateInventory($itemAdjust, $itemAdjustItem, $isUpdate, $oldQuantity);
    }

    /**
     * Update item adjust items individually
     */
    private function updateItemAdjustItems(ItemAdjust $itemAdjust, array $items): void
    {
        $processedItemIds = [];

        foreach ($items as $itemData) {
            if (isset($itemData['id'])) {
                // Update existing item
                $itemAdjustItem = ItemAdjustItem::where('id', $itemData['id'])
                    ->where('item_adjust_id', $itemAdjust->id)
                    ->first();

                if ($itemAdjustItem) {
                    $oldQuantity = $itemAdjustItem->quantity;

                    // Update the item
                    $quantity = $itemData['quantity'];

                    $itemAdjustItem->update([
                        'quantity' => $quantity,
                        'note' => $itemData['note'] ?? $itemAdjustItem->note,
                    ]);

                    // Update related data if quantity changed
                    if ((float)$oldQuantity != (float)$quantity) {
                        $this->processItemAdjustItemRelatedData($itemAdjust, $itemAdjustItem, true, $oldQuantity);
                    }

                    $processedItemIds[] = $itemAdjustItem->id;
                }
            } else {
                // Create new item
                $itemAdjustItem = $this->createItemAdjustItem($itemAdjust, $itemData);
                $this->processItemAdjustItemRelatedData($itemAdjust, $itemAdjustItem);
                $processedItemIds[] = $itemAdjustItem->id;
            }
        }

        // Handle removed items - restore inventory
        $removedItems = $itemAdjust->itemAdjustItems()
            ->whereNotIn('id', $processedItemIds)
            ->get();

        foreach ($removedItems as $removedItem) {
            $this->handleItemAdjustItemDeletion($itemAdjust, $removedItem);
        }

        // Delete the removed items from database
        $itemAdjust->itemAdjustItems()
            ->whereNotIn('id', $processedItemIds)
            ->delete();
    }

    /**
     * Create a single item adjust item
     */
    private function createItemAdjustItem(ItemAdjust $itemAdjust, array $itemData): ItemAdjustItem
    {
        $preparedData = $this->prepareItemAdjustItemData($itemAdjust, $itemData);
        return ItemAdjustItem::create($preparedData);
    }

    /**
     * Prepare item adjust item data
     */
    private function prepareItemAdjustItemData(ItemAdjust $itemAdjust, array $itemData): array
    {
        $item = Item::findOrFail($itemData['item_id']);

        return [
            'item_adjust_id' => $itemAdjust->id,
            'item_id' => $itemData['item_id'],
            'item_code' => $item->code,
            'quantity' => $itemData['quantity'],
            'note' => $itemData['note'] ?? null,
        ];
    }

    /**
     * Update inventory for item adjust item (add or subtract based on type)
     */
    private function updateInventory(ItemAdjust $itemAdjust, ItemAdjustItem $itemAdjustItem, bool $isUpdate = false, ?float $oldQuantity = null): void
    {
        if ($isUpdate && $oldQuantity !== null) {
            // For updates, adjust inventory by the difference
            $quantityDifference = $itemAdjustItem->quantity - $oldQuantity;
            if ($quantityDifference != 0) {
                if ($itemAdjust->type === 'Add') {
                    InventoryService::add(
                        $itemAdjustItem->item_id,
                        $itemAdjust->warehouse_id,
                        $quantityDifference
                    );
                } else {
                    InventoryService::subtract(
                        $itemAdjustItem->item_id,
                        $itemAdjust->warehouse_id,
                        $quantityDifference
                    );
                }
            }
        } else {
            // Add or subtract based on adjustment type
            if ($itemAdjust->type === 'Add') {
                InventoryService::add(
                    $itemAdjustItem->item_id,
                    $itemAdjust->warehouse_id,
                    $itemAdjustItem->quantity
                );
            } else {
                InventoryService::subtract(
                    $itemAdjustItem->item_id,
                    $itemAdjust->warehouse_id,
                    $itemAdjustItem->quantity
                );
            }
        }
    }

    /**
     * Handle item adjust item deletion - reverse the adjustment
     */
    private function handleItemAdjustItemDeletion(ItemAdjust $itemAdjust, ItemAdjustItem $itemAdjustItem): void
    {
        // Reverse the adjustment: if it was Add, now subtract; if it was Subtract, now add
        if ($itemAdjust->type === 'Add') {
            InventoryService::subtract(
                $itemAdjustItem->item_id,
                $itemAdjust->warehouse_id,
                $itemAdjustItem->quantity
            );
        } else {
            InventoryService::add(
                $itemAdjustItem->item_id,
                $itemAdjust->warehouse_id,
                $itemAdjustItem->quantity
            );
        }
    }
}
