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
            'quantity' => $this->quantity,
            'cost_price' => $this->cost_price,
            'price' => $this->price,
            'price_usd' => $this->price_usd,
            'tax_percent' => $this->tax_percent,
            'ttc_price' => $this->ttc_price,
            'ttc_price_usd' => $this->ttc_price_usd,
            'discount_percent' => $this->discount_percent,
            'unit_discount_amount' => $this->unit_discount_amount,
            'unit_discount_amount_usd' => $this->unit_discount_amount_usd,
            'discount_amount' => $this->discount_amount,
            'discount_amount_usd' => $this->discount_amount_usd,
            'total_price' => $this->total_price,
            'total_price_usd' => $this->total_price_usd,
            'unit_profit' => $this->unit_profit,
            'total_profit' => $this->total_profit,
            'note' => $this->note,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,

            'item' => $this->whenLoaded('item'),
        ];
    }
}