<?php

namespace App\Http\Resources\Api\Reports\Sales;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemsSaleReportResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'item_id'               => $this->item_id,
            'item_code'             => $this->item_code,
            'item_name'             => $this->item_name,
            'item_description'      => $this->item_description,
            'category_name'         => $this->category_name,
            'family_name'           => $this->family_name,
            'group_name'            => $this->group_name,
            'type_name'             => $this->type_name,
            'brand_name'            => $this->brand_name,
            'supplier_name'         => $this->supplier_name,
            'total_quantity'        => (float) $this->total_quantity,
            'total_sale_amount'     => round((float) $this->total_sale_amount, 2),
            'total_sale_amount_usd' => round((float) $this->total_sale_amount_usd, 2),
            'total_profit'          => round((float) $this->total_profit, 2),
            'profit_percent'        => round((float) $this->profit_percent, 2),
        ];
    }
}
