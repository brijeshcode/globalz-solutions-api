<?php

namespace App\Http\Resources\Api\Items;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Items\ItemOffer
 */
class ItemOfferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,

            // Item being purchased
            'item_id' => $this->item_id,
            'item' => $this->whenLoaded('item', fn () => [
                'id'         => $this->item->id,
                'code'       => $this->item->code,
                'short_name' => $this->item->short_name,
                'description' => $this->item->description,
                'total_quantity'      => $this->warehouseTotal($this->item),
                'warehouse_quantities' => $this->warehouseQuantities($this->item),
            ]),

            // Item given for free
            'free_item_id' => $this->free_item_id,
            'free_item' => $this->whenLoaded('freeItem', fn () => [
                'id'         => $this->freeItem->id,
                'code'       => $this->freeItem->code,
                'short_name' => $this->freeItem->short_name,
                'description' => $this->freeItem->description,
                'total_quantity'      => $this->warehouseTotal($this->freeItem),
                'warehouse_quantities' => $this->warehouseQuantities($this->freeItem),
            ]),

            // Dates
            'date'          => $this->date?->format('Y-m-d'),
            'validity_date' => $this->validity_date?->format('Y-m-d'),

            // Offer terms
            'minimum_quantity'    => $this->minimum_quantity,
            'free_quantity'       => $this->free_quantity,
            'usage_limit'         => $this->usage_limit,
            'used_count'          => $this->used_count,
            'can_change_quantity' => $this->can_change_quantity,
            'allow_multiple'      => $this->allow_multiple,

            // Status
            'is_active'            => $this->is_active,
            'is_expired'           => $this->isExpired(),
            'is_usage_limit_reached' => $this->isUsageLimitReached(),
            'is_available'         => $this->isAvailable(),

            // Audit
            'created_by' => $this->whenLoaded('createdBy', fn () => [
                'id'   => $this->createdBy->id,
                'name' => $this->createdBy->name,
            ]),
            'updated_by' => $this->whenLoaded('updatedBy', fn () => [
                'id'   => $this->updatedBy->id,
                'name' => $this->updatedBy->name,
            ]),

            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'deleted_at' => $this->when(filled($this->deleted_at), $this->deleted_at?->format('Y-m-d H:i:s')),
        ];
    }

    /**
     * Per-warehouse stock for the given item, used to confirm offer availability.
     * Returns an empty array when inventories were not eager-loaded.
     *
     * @return array<int, array{warehouse_id: int, warehouse_name: string|null, quantity: float}>
     */
    private function warehouseQuantities(\App\Models\Items\Item $item): array
    {
        if (!$item->relationLoaded('inventories')) {
            return [];
        }

        return $item->inventories->map(fn ($inventory) => [
            'warehouse_id'   => $inventory->warehouse_id,
            'warehouse_name' => $inventory->relationLoaded('warehouse') ? $inventory->warehouse?->name : null,
            'quantity'       => $inventory->quantity,
        ])->values()->all();
    }

    /**
     * Total stock across all warehouses; null when inventories were not eager-loaded.
     */
    private function warehouseTotal(\App\Models\Items\Item $item): ?float
    {
        if (!$item->relationLoaded('inventories')) {
            return null;
        }

        return (float) $item->inventories->sum('quantity');
    }
}
