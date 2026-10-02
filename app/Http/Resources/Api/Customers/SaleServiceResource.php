<?php

namespace App\Http\Resources\Api\Customers;

use App\Helpers\FeatureHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Customers\SaleService
 */
class SaleServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sale_id' => $this->sale_id,
            'service_id' => $this->service_id,
            'hsn' => $this->when(FeatureHelper::isHsnField(), $this->hsn),
            'date' => $this->date,
            'quantity' => $this->quantity + 0,
            'unit_cost_price' => $this->unit_cost_price + 0,
            'unit_price' => $this->unit_price + 0,
            'unit_price_usd' => $this->unit_price_usd + 0,
            'discount_percent' => $this->discount_percent + 0,
            'unit_discount_amount' => $this->unit_discount_amount + 0,
            'unit_discount_amount_usd' => $this->unit_discount_amount_usd + 0,
            'total_discount_amount' => $this->total_discount_amount + 0,
            'total_discount_amount_usd' => $this->total_discount_amount_usd + 0,
            'unit_net_sell_price' => $this->unit_net_sell_price + 0,
            'unit_net_sell_price_usd' => $this->unit_net_sell_price_usd + 0,
            'tax_percent' => $this->tax_percent + 0,
            'tax_label' => $this->tax_label,
            'unit_tax_amount' => $this->unit_tax_amount + 0,
            'unit_tax_amount_usd' => $this->unit_tax_amount_usd + 0,
            'total_tax_amount' => $this->total_tax_amount + 0,
            'total_tax_amount_usd' => $this->total_tax_amount_usd + 0,
            'unit_ttc_price' => $this->unit_ttc_price + 0,
            'unit_ttc_price_usd' => $this->unit_ttc_price_usd + 0,
            'total_net_sell_price' => $this->total_net_sell_price + 0,
            'total_net_sell_price_usd' => $this->total_net_sell_price_usd + 0,
            'total_price' => $this->total_price + 0,
            'total_price_usd' => $this->total_price_usd + 0,
            'unit_profit' => $this->unit_profit + 0,
            'total_profit' => $this->total_profit + 0,
            'note' => $this->note,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,

            'service' => $this->whenLoaded('service'),
        ];
    }
}
