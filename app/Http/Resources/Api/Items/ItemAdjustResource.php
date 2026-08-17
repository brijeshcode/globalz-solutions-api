<?php

namespace App\Http\Resources\Api\Items;

use Illuminate\Http\Request;
use App\Helpers\FeatureHelper;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Items\ItemAdjust
 */
class ItemAdjustResource extends JsonResource
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
            'is_synced_to_old' => $this->when(FeatureHelper::isSyncin(), fn () => $this->is_synced_to_old),
            'code' => $this->code,
            'prefix' => $this->prefix,
            'type' => $this->type,
            'date' => $this->date->format('Y-m-d H:i:s'),

            // Computed attributes
            'total_items_count' => $this->total_items_count,
            'item_adjust_code' => $this->item_adjust_code,
            'total_quantity' => $this->total_quantity,
            'has_items' => $this->has_items,

            'note' => $this->note,

            // Warehouse relationship
            'warehouse_id' => $this->warehouse_id,
            'warehouse' => $this->whenLoaded('warehouse', function () {
                return [
                    'id' => $this->warehouse->id,
                    'name' => $this->warehouse->name,
                ];
            }),

            // Adjust items
            'items' => $this->whenLoaded('itemAdjustItems', function () {
                return $this->itemAdjustItems->map(function ($item) {
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
            'deleted_at' => $this->when(filled($this->deleted_at), $this->deleted_at?->format('Y-m-d H:i:s')),
        ];
    }
}
