<?php

namespace App\Http\Resources\Api\Items;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemTransferResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'prefix' => $this->prefix,
            'date' => $this->date?->format('Y-m-d H:i:s'),

            // Computed attributes
            'total_items_count' => $this->getTotalItemsCountAttribute(),
            'item_transfer_code' => $this->getItemTransferCodeAttribute(),
            'total_quantity' => $this->getTotalQuantityAttribute(),
            'has_items' => $this->getHasItemsAttribute(),

            'note' => $this->note,

            // Warehouse relationships
            'from_warehouse_id' => $this->from_warehouse_id,
            'from_warehouse' => $this->whenLoaded('fromWarehouse', function () {
                return [
                    'id' => $this->fromWarehouse->id,
                    'name' => $this->fromWarehouse->name,
                    'address' => $this->when($this->fromWarehouse->address, $this->fromWarehouse->address),
                ];
            }),

            'to_warehouse_id' => $this->to_warehouse_id,
            'to_warehouse' => $this->whenLoaded('toWarehouse', function () {
                return [
                    'id' => $this->toWarehouse->id,
                    'name' => $this->toWarehouse->name,
                    'address' => $this->when($this->toWarehouse->address, $this->toWarehouse->address),
                ];
            }),

            // Transfer items
            'items' => $this->whenLoaded('itemTransferItems', function () {
                return $this->itemTransferItems->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'item_id' => $item->item_id,
                        'item_code' => $item->item_code,
                        'quantity' => $item->quantity,
                        'note' => $item->note,

                        'item' => $this->when($item->relationLoaded('item'), function () use ($item) {
                            return [
                                'id' => $item->item->id,
                                'code' => $item->item->code,
                                'short_name' => $item->item->short_name,
                                'description' => $item->item->description ?? null,
                            ];
                        }),
                    ];
                });
            }),

            // Audit fields
            'created_by' => $this->whenLoaded('createdBy', function () {
                return [
                    'id' => $this->createdBy->id,
                    'name' => $this->createdBy->name,
                ];
            }),

            'updated_by' => $this->whenLoaded('updatedBy', function () {
                return [
                    'id' => $this->updatedBy->id,
                    'name' => $this->updatedBy->name,
                ];
            }),

            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            'deleted_at' => $this->when($this->deleted_at, $this->deleted_at?->format('Y-m-d H:i:s')),
        ];
    }
}
