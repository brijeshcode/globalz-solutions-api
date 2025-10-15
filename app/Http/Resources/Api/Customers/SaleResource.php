<?php

namespace App\Http\Resources\Api\Customers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'sale_code' => $this->sale_code,
            'date' => $this->date,
            'prefix' => $this->prefix,
            'salesperson_id' => $this->salesperson_id,
            'customer_id' => $this->customer_id,
            'currency_id' => $this->currency_id,
            'warehouse_id' => $this->warehouse_id,
            'customer_payment_term_id' => $this->customer_payment_term_id,
            'customer_last_payment_receipt_id' => $this->customer_last_payment_receipt_id,
            'client_po_number' => $this->client_po_number,
            'currency_rate' => $this->currency_rate,
            'credit_limit' => $this->credit_limit,
            'outStanding_balance' => $this->outStanding_balance,
            'sub_total' => $this->sub_total,
            'sub_total_usd' => $this->sub_total_usd,
            'discount_amount' => $this->discount_amount,
            'discount_amount_usd' => $this->discount_amount_usd,
            'total' => $this->total,
            'total_usd' => $this->total_usd,
            'total_profit' => $this->total_profit,
            'value_date' => $this->value_date,
            'total_volume_cbm' => $this->total_volume_cbm,
            'total_weight_kg' => $this->total_weight_kg,
            'total_tax_amount' => $this->total_tax_amount,
            'total_tax_amount_usd' => $this->total_tax_amount_usd,
            'local_curreny_rate' => $this->local_curreny_rate,
            'invoice_tax_label' => $this->invoice_tax_label,
            'invoice_nb1' => $this->invoice_nb1,
            'invoice_nb2' => $this->invoice_nb2,
            'note' => $this->note,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,

            // Shipping status
            'status' => $this->status,
            
            'sale_items' => SaleItemResource::collection($this->whenLoaded('saleItems')),
            'items' => SaleItemResource::collection($this->whenLoaded('saleItems')),
            'warehouse' => $this->whenLoaded('warehouse'),
            'currency' => $this->whenLoaded('currency'),
            'salesperson' => $this->whenLoaded('salesperson'),
            'customer' => $this->whenLoaded('customer'),
        ];
    }
}
