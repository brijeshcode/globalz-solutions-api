<?php

namespace App\Http\Resources\Api\Items;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemListResource extends JsonResource
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
            'current_cost' => $this->getCurrentCostAttribute(),
            'current_price' => $this->getCurrentPriceAttribute(),
            'current_quantity' => $this->getCurrentQuantityAttribute(),
            'is_low_stock' => $this->getIsLowStockAttribute(),

            // Helper methods display
            'is_cost_calculated_by_weighted_average' => $this->isCostCalculatedByWeightedAverage(),
            'is_cost_calculated_by_last_cost' => $this->isCostCalculatedByLastCost(),

             
            // Display helpers for frontend
            'display_name' => $this->short_name ?: $this->description,
            'full_display_name' => $this->code . ' - ' . ($this->short_name ?: $this->description),
            
            // Pricing calculations for display
            'profit_margin' => $this->calculateProfitMargin(),
            'markup_percentage' => $this->calculateMarkupPercentage(),
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
}