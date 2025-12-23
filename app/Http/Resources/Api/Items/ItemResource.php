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
        $data = [];
        $selectedFields = $this->getSelectedFields();

        // Always include default fields
        $data['id'] = $this->id;

        if ($this->fieldWasSelected('code', $selectedFields)) {
            $data['code'] = $this->code;
        }

        if ($this->fieldWasSelected('short_name', $selectedFields)) {
            $data['short_name'] = $this->short_name;
        }

        if ($this->fieldWasSelected('description', $selectedFields)) {
            $data['description'] = $this->description;
        }

        // Optional fields if they were selected
        if ($this->fieldWasSelected('volume', $selectedFields)) {
            $data['volume'] = $this->volume !== null ? (float) $this->volume : null;
        }

        if ($this->fieldWasSelected('weight', $selectedFields)) {
            $data['weight'] = $this->weight !== null ? (float) $this->weight : null;
        }

        if ($this->fieldWasSelected('barcode', $selectedFields)) {
            $data['barcode'] = $this->barcode;
        }
        if ($this->fieldWasSelected('tax_code_id', $selectedFields)) {
            $data['tax_code_id'] = $this->tax_code_id;
        }

        if ($this->fieldWasSelected('base_cost', $selectedFields)) {
            $data['base_cost'] = $this->base_cost !== null ? (float) $this->base_cost : null;
        }

        if ($this->fieldWasSelected('base_sell', $selectedFields)) {
            $data['base_sell'] = $this->base_sell !== null ? (float) $this->base_sell : null;
        }

        if ($this->fieldWasSelected('sell_price', $selectedFields)) {
            $data['sell_price'] = $this->sell_price !== null ? (float) $this->sell_price : 0;
        }

        if ($this->fieldWasSelected('starting_price', $selectedFields)) {
            $data['starting_price'] = $this->starting_price !== null ? (float) $this->starting_price : null;
        }

        if ($this->fieldWasSelected('starting_quantity', $selectedFields)) {
            $data['starting_quantity'] = $this->starting_quantity !== null ? (float) $this->starting_quantity : 0;
        }

        if ($this->fieldWasSelected('low_quantity_alert', $selectedFields)) {
            $data['low_quantity_alert'] = $this->low_quantity_alert !== null ? (float) $this->low_quantity_alert : null;
        }

        if ($this->fieldWasSelected('cost_calculation', $selectedFields)) {
            $data['cost_calculation'] = $this->cost_calculation;
            $data['cost_calculation_display'] = ucwords(str_replace('_', ' ', $this->cost_calculation ?? ''));
        }

        if ($this->fieldWasSelected('notes', $selectedFields)) {
            $data['notes'] = $this->notes;
        }

        if ($this->fieldWasSelected('is_active', $selectedFields)) {
            $data['is_active'] = (bool) $this->is_active;
        }

        // Always include default relations
        $data['item_unit'] = $this->whenLoaded('itemUnit', function () {
            return [
                'id' => $this->itemUnit->id,
                'name' => $this->itemUnit->name,
                'symbol' => $this->itemUnit->symbol ?? null,
            ];
        });

        $data['current_cost'] = $this->whenLoaded('itemPrice', function () {
            return $this->itemPrice ? (float) $this->itemPrice->price_usd : (float) ($this->base_cost ?? 0);
        }, (float) ($this->base_cost ?? 0));

        $data['item_price_data'] = $this->whenLoaded('itemPrice', function () {
            return $this->itemPrice ? [
                'price_usd' => $this->itemPrice->price_usd,
                'effective_date' => $this->itemPrice->effective_date,
                'formatted_price' => $this->itemPrice->formatted_price,
                'is_recent' => $this->itemPrice->is_recent,
                'age_in_days' => $this->itemPrice->age_in_days,
            ] : null;
        });

        $data['total_inventory_quantity'] = $this->whenLoaded('inventories', function () {
            return $this->total_inventory_quantity;
        });

        $data['current_quantity'] = $this->getDisplayQuantity($request);

        $data['warehouse_inventory_quantity'] = $this->when(
            $request->has('warehouse_id') && isset($this->warehouse_inventory_quantity),
            $this->warehouse_inventory_quantity ?? 0
        );

        $data['warehouse_inventories'] = $this->whenLoaded('inventories', function () {
            return $this->inventories->map(function ($inventory) {
                return [
                    'warehouse_id' => $inventory->warehouse_id,
                    'warehouse_name' => $inventory->warehouse->name ?? null,
                    'quantity' => $inventory->quantity,
                ];
            });
        });

        // Optional relations only when loaded
        $data['item_type'] = $this->whenLoaded('itemType', function () {
            return [
                'id' => $this->itemType->id,
                'name' => $this->itemType->name,
            ];
        });

        $data['item_family'] = $this->whenLoaded('itemFamily', function () {
            return $this->itemFamily ? [
                'id' => $this->itemFamily->id,
                'name' => $this->itemFamily->name,
            ] : null;
        });

        $data['item_group'] = $this->whenLoaded('itemGroup', function () {
            return $this->itemGroup ? [
                'id' => $this->itemGroup->id,
                'name' => $this->itemGroup->name,
            ] : null;
        });

        $data['item_category'] = $this->whenLoaded('itemCategory', function () {
            return $this->itemCategory ? [
                'id' => $this->itemCategory->id,
                'name' => $this->itemCategory->name,
            ] : null;
        });

        $data['item_brand'] = $this->whenLoaded('itemBrand', function () {
            return $this->itemBrand ? [
                'id' => $this->itemBrand->id,
                'name' => $this->itemBrand->name,
            ] : null;
        });

        $data['item_profit_margin'] = $this->whenLoaded('itemProfitMargin', function () {
            return $this->itemProfitMargin ? [
                'id' => $this->itemProfitMargin->id,
                'name' => $this->itemProfitMargin->name,
            ] : null;
        });

        $data['supplier'] = $this->whenLoaded('supplier', function () {
            return $this->supplier ? [
                'id' => $this->supplier->id,
                'code' => $this->supplier->code,
                'name' => $this->supplier->name,
            ] : null;
        });

        $data['tax_code'] = $this->whenLoaded('taxCode', function () {
            return [
                'id' => $this->taxCode->id,
                'name' => $this->taxCode->name,
                'rate' => $this->taxCode->tax_percent ?? null,
            ];
        });

        $data['created_by'] = $this->whenLoaded('createdBy', function () {
            return $this->createdBy ? [
                'id' => $this->createdBy->id,
                'name' => $this->createdBy->name,
            ] : null;
        });

        $data['updated_by'] = $this->whenLoaded('updatedBy', function () {
            return $this->updatedBy ? [
                'id' => $this->updatedBy->id,
                'name' => $this->updatedBy->name,
            ] : null;
        });

        $data['documents'] = $this->whenLoaded('documents', function () {
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
                    'file_size_human' => $document->file_size_human,
                    'thumbnail_url' => $document->thumbnail_url,
                    'download_url' => $document->download_url,
                    'preview_url' => $document->preview_url,
                    'created_at' => $document->created_at?->format('Y-m-d H:i:s'),
                    'updated_at' => $document->updated_at?->format('Y-m-d H:i:s'),
                    'deleted_at' => $document->deleted_at?->format('Y-m-d H:i:s'),
                ];
            });
        });

        // Computed fields only if base data was selected
        if ($this->fieldWasSelected('base_cost', $selectedFields) && $this->fieldWasSelected('base_sell', $selectedFields)) {
            $profitMargin = $this->calculateProfitMargin();
            $data['profit_margin'] = $profitMargin;

            $markupPercentage = $this->calculateMarkupPercentage();
            $data['markup_percentage'] = $markupPercentage;
        }

        // Helper display fields (always include if core fields were selected)
        if ($this->fieldWasSelected('short_name', $selectedFields) || $this->fieldWasSelected('description', $selectedFields)) {
            $data['display_name'] = $this->short_name ?: $this->description;
        }

        if ($this->fieldWasSelected('code', $selectedFields) && ($this->fieldWasSelected('short_name', $selectedFields) || $this->fieldWasSelected('description', $selectedFields))) {
            $data['full_display_name'] = $this->code . ' - ' . ($this->short_name ?: $this->description);
        }

        // Timestamps if they were selected
        if ($this->fieldWasSelected('created_at', $selectedFields)) {
            $data['created_at'] = $this->created_at?->toISOString();
        }

        if ($this->fieldWasSelected('updated_at', $selectedFields)) {
            $data['updated_at'] = $this->updated_at?->toISOString();
        }

        if ($this->fieldWasSelected('deleted_at', $selectedFields)) {
            $data['deleted_at'] = $this->deleted_at?->toISOString();
        }

        return $data;
    }

    /**
     * Get the selected fields from the model
     */
    private function getSelectedFields(): array
    {
        // Get the attributes that were actually selected in the query
        return array_keys($this->getAttributes());
    }

    /**
     * Check if a field was selected in the query
     */
    private function fieldWasSelected(string $field, array $selectedFields): bool
    {
        // Default fields are always included
        $defaultFields = ['id', 'description', 'code', 'short_name', 'item_unit_id'];

        if (in_array($field, $defaultFields)) {
            return true;
        }

        // Check if the field was explicitly selected
        return in_array($field, $selectedFields);
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