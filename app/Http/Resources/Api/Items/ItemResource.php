<?php

namespace App\Http\Resources\Api\Items;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemResource extends JsonResource
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
            
            // Main Information
            'code' => $this->code,
            'short_name' => $this->short_name,
            'description' => $this->description,
            
            // Classification
            'item_type' => $this->whenLoaded('itemType', function () {
                return [
                    'id' => $this->itemType->id,
                    'name' => $this->itemType->name,
                ];
            }),
            'item_family' => $this->whenLoaded('itemFamily', function () {
                return $this->itemFamily ? [
                    'id' => $this->itemFamily->id,
                    'name' => $this->itemFamily->name,
                ] : null;
            }),
            'item_group' => $this->whenLoaded('itemGroup', function () {
                return $this->itemGroup ? [
                    'id' => $this->itemGroup->id,
                    'name' => $this->itemGroup->name,
                ] : null;
            }),
            'item_category' => $this->whenLoaded('itemCategory', function () {
                return $this->itemCategory ? [
                    'id' => $this->itemCategory->id,
                    'name' => $this->itemCategory->name,
                ] : null;
            }),
            'item_brand' => $this->whenLoaded('itemBrand', function () {
                return $this->itemBrand ? [
                    'id' => $this->itemBrand->id,
                    'name' => $this->itemBrand->name,
                ] : null;
            }),
            'item_profit_margin' => $this->whenLoaded('itemProfitMargin', function () {
                return $this->itemProfitMargin ? [
                    'id' => $this->itemProfitMargin->id,
                    'name' => $this->itemProfitMargin->name,
                ] : null;
            }),
            'item_unit' => $this->whenLoaded('itemUnit', function () {
                return [
                    'id' => $this->itemUnit->id,
                    'name' => $this->itemUnit->name,
                    'symbol' => $this->itemUnit->symbol ?? null,
                ];
            }),
            'supplier' => $this->whenLoaded('supplier', function () {
                return $this->supplier ? [
                    'id' => $this->supplier->id,
                    'code' => $this->supplier->code,
                    'name' => $this->supplier->name,
                ] : null;
            }),
            'tax_code' => $this->whenLoaded('taxCode', function () {
                return [
                    'id' => $this->taxCode->id,
                    'name' => $this->taxCode->name,
                    'rate' => $this->taxCode->tax_percent ?? null,
                ];
            }),

            // Physical Properties
            'volume' => $this->volume ? (float) $this->volume : null,
            'weight' => $this->weight ? (float) $this->weight : null,
            'barcode' => $this->barcode,

            // Pricing Information
            'base_cost' => $this->base_cost ? (float) $this->base_cost : null,
            'base_sell' => $this->base_sell ? (float) $this->base_sell : null,
            'starting_price' => $this->starting_price ? (float) $this->starting_price : null,

            // Inventory Management
            'starting_quantity' => $this->starting_quantity ? (float) $this->starting_quantity : 0,
            'low_quantity_alert' => $this->low_quantity_alert ? (float) $this->low_quantity_alert : null,

            // Cost Calculation
            'cost_calculation' => $this->cost_calculation,
            'cost_calculation_display' => ucwords(str_replace('_', ' ', $this->cost_calculation)),

            // Additional Information
            'notes' => $this->notes,

            // System Fields
            'is_active' => (bool) $this->is_active,

            // Computed Properties
            'current_cost' => $this->whenLoaded('itemPrice', function () {
                return $this->itemPrice ? (float) $this->itemPrice->price_usd : (float) ($this->base_cost ?? 0);
            }, (float) ($this->base_cost ?? 0)),
            'current_price' => (float) ($this->base_sell ?? 0),
            'current_quantity' => $this->getDisplayQuantity($request),
            'is_low_stock' => $this->is_low_stock,

            // Helper methods display
            'is_cost_calculated_by_weighted_average' => $this->isCostCalculatedByWeightedAverage(),
            'is_cost_calculated_by_last_cost' => $this->isCostCalculatedByLastCost(),

            // Audit Information
            'created_by' => $this->whenLoaded('createdBy', function () {
                return $this->createdBy ? [
                    'id' => $this->createdBy->id,
                    'name' => $this->createdBy->name,
                ] : null;
            }),
            'updated_by' => $this->whenLoaded('updatedBy', function () {
                return $this->updatedBy ? [
                    'id' => $this->updatedBy->id,
                    'name' => $this->updatedBy->name,
                ] : null;
            }),

            // Timestamps
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
            'deleted_at' => $this->deleted_at?->toISOString(),

            // Display helpers for frontend
            'display_name' => $this->short_name ?: $this->description,
            'full_display_name' => $this->code . ' - ' . ($this->short_name ?: $this->description),
            
            // Pricing calculations for display
            'profit_margin' => $this->calculateProfitMargin(),
            'markup_percentage' => $this->calculateMarkupPercentage(),

             // Documents
            'documents' => $this->whenLoaded('documents', function () {
                return $this->documents->map(function ($document) {
                    return [
                        'id' => $document->id,
                        'documentable_type' => $document->documentable_type,
                        'documentable_id' => $document->documentable_id,
                        'original_name' => $document->original_name,
                        'file_name' => $document->file_name,
                        'file_path' => $document->file_path,
                        'file_size' => $document->file_size,
                        'mime_type' => $document->mime_type,
                        'file_extension' => $document->file_extension,
                        'title' => $document->title,
                        'description' => $document->description,
                        'document_type' => $document->document_type,
                        'folder' => $document->folder,
                        'tags' => $document->tags,
                        'sort_order' => $document->sort_order,
                        'is_public' => $document->is_public,
                        'is_featured' => $document->is_featured,
                        'metadata' => $document->metadata,
                        'uploaded_by' => $document->uploaded_by,
                        // Appended attributes from Document model
                        'file_size_human' => $document->file_size_human,
                        'thumbnail_url' => $document->thumbnail_url,
                        'download_url' => $document->download_url,
                        'preview_url' => $document->preview_url,
                        'created_at' => $document->created_at?->format('Y-m-d H:i:s'),
                        'updated_at' => $document->updated_at?->format('Y-m-d H:i:s'),
                        'deleted_at' => $document->deleted_at?->format('Y-m-d H:i:s'),
                    ];
                });
            }),

            // Inventory related data
            'total_inventory_quantity' => $this->whenLoaded('inventories', function () {
                return $this->total_inventory_quantity;
            }),
            'warehouse_inventory_quantity' => $this->when(
                $request->has('warehouse_id') && isset($this->warehouse_inventory_quantity),
                $this->warehouse_inventory_quantity ?? 0
            ),
            'warehouse_inventories' => $this->whenLoaded('inventories', function () {
                return $this->inventories->map(function ($inventory) {
                    return [
                        'warehouse_id' => $inventory->warehouse_id,
                        'warehouse_name' => $inventory->warehouse->name ?? null,
                        'quantity' => $inventory->quantity,
                    ];
                });
            }),

            // Item Price data  
            'item_price_data' => $this->whenLoaded('itemPrice', function () {
                return $this->itemPrice ? [
                    'price_usd' => $this->itemPrice->price_usd,
                    'effective_date' => $this->itemPrice->effective_date,
                    'formatted_price' => $this->itemPrice->formatted_price,
                    'is_recent' => $this->itemPrice->is_recent,
                    'age_in_days' => $this->itemPrice->age_in_days,
                ] : null;
            }),
        ];
    }

    /**
     * Calculate profit margin percentage
     */
    private function calculateProfitMargin(): ?float
    {
        if (!$this->base_cost || !$this->base_sell || $this->base_sell == 0) {
            return null;
        }

        return round((($this->base_sell - $this->base_cost) / $this->base_sell) * 100, 2);
    }

    /**
     * Calculate markup percentage
     */
    private function calculateMarkupPercentage(): ?float
    {
        if (!$this->base_cost || !$this->base_sell || $this->base_cost == 0) {
            return null;
        }

        return round((($this->base_sell - $this->base_cost) / $this->base_cost) * 100, 2);
    }

    /**
     * Get display quantity based on warehouse filter
     */
    private function getDisplayQuantity(\Illuminate\Http\Request $request): int|float
    {
        // If filtering by warehouse and warehouse_inventory_quantity is available, use it
        if ($request->has('warehouse_id') && isset($this->warehouse_inventory_quantity)) {
            return $this->warehouse_inventory_quantity;
        }
        
        // Otherwise, use total inventory quantity
        return $this->total_inventory_quantity ?? 0;
    }
}