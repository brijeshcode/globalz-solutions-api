<?php

namespace App\Http\Resources\Api\Setups;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaxCodeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'name' => $this->name,
            'description' => $this->description,
            'item_count' => $this->items_count,
            'tax_percent' => $this->tax_percent,
            'tax_rate' => $this->tax_rate, // Calculated accessor (tax_percent / 100)
            'type' => $this->type,
            'type_label' => ucfirst($this->type), // Capitalize first letter
            
            // Status fields
            'is_active' => $this->is_active,
            'is_default' => $this->is_default,
            
            // System Fields
            'created_by' => [
                'id' => $this->createdBy?->id,
                'name' => $this->createdBy?->name,
            ],
            'updated_by' => [
                'id' => $this->updatedBy?->id,
                'name' => $this->updatedBy?->name,
            ],
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
            
            // Conditional fields for detailed views
            $this->mergeWhen($request->routeIs('tax-codes.show'), [
                'usage_count' => $this->whenLoaded('items', function () {
                    return $this->items->count();
                }),
                'items' => $this->whenLoaded('items', function () {
                    return $this->items->map(function ($item) {
                        return [
                            'id' => $item->id,
                            'code' => $item->code,
                            'description' => $item->description,
                        ];
                    });
                }),
            ]),
            
            // Helper calculations (for frontend display)
            'display_name' => $this->code . ' - ' . $this->name . ' (' . $this->tax_percent . '%)',
            'formatted_rate' => number_format($this->tax_percent, 2) . '%',
        ];
    }
}