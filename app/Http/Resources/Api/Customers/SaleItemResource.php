<?php

namespace App\Http\Resources\Api\Customers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'item_code' => $this->item_code,
            'sale_id' => $this->sale_id,
            'item_id' => $this->item_id,
            'quantity' => $this->quantity + 0,
            'cost_price' => $this->cost_price + 0,
            'price' => $this->price + 0,
            'price_usd' => $this->price_usd + 0,
            'tax_percent' => $this->tax_percent + 0,
            'tax_label' => $this->tax_label,
            'tax_amount' => $this->tax_amount + 0,
            'tax_amount_usd' => $this->tax_amount_usd + 0,
            'ttc_price' => $this->ttc_price + 0,
            'ttc_price_usd' => $this->ttc_price_usd + 0,
            'discount_percent' => $this->discount_percent + 0,
            'unit_discount_amount' => $this->unit_discount_amount + 0,
            'unit_discount_amount_usd' => $this->unit_discount_amount_usd + 0,
            'discount_amount' => $this->discount_amount + 0,
            'discount_amount_usd' => $this->discount_amount_usd + 0,
            'total_price' => $this->total_price + 0,
            'total_price_usd' => $this->total_price_usd + 0,
            'unit_profit' => $this->unit_profit + 0,
            'total_profit' => $this->total_profit + 0,
            'unit_volume_cbm' => $this->unit_volume_cbm + 0,
            'unit_weight_kg' => $this->unit_weight_kg + 0,
            'total_volume_cbm' => $this->total_volume_cbm + 0,
            'total_weight_kg' => $this->total_weight_kg + 0,
            'note' => $this->note,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,

            'item' => $this->whenLoaded('item'),
        ];
    }
}