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
            'currency_rate' => $this->currency_rate + 0,
            'credit_limit' => $this->credit_limit + 0 ,
            'outStanding_balance' => $this->outStanding_balance + 0,
            'sub_total' => floatval($this->sub_total),
            'sub_total_usd' => $this->sub_total_usd + 0,
            'discount_amount' => $this->discount_amount + 0,
            'discount_amount_usd' => $this->discount_amount_usd + 0,
            'total' => $this->total + 0,
            'total_usd' => $this->total_usd + 0,
            'total_profit' => $this->total_profit + 0,
            'value_date' => $this->value_date + 0,
            'total_volume_cbm' => $this->total_volume_cbm + 0,
            'total_weight_kg' => $this->total_weight_kg + 0,
            'total_tax_amount' => $this->total_tax_amount + 0,
            'total_tax_amount_usd' => $this->total_tax_amount_usd + 0,
            'local_curreny_rate' => $this->local_curreny_rate + 0,
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
            'currency' => $this->whenLoaded('currency', function () {
                return [
                    'id' => $this->currency->id,
                    'name' => $this->currency->name,
                    'code' => $this->currency->code,
                    'symbol' => $this->when($this->currency->symbol, $this->currency->symbol),
                    'calculation_type' => $this->currency->calculation_type,
                    'symbol_position' => $this->currency->symbol_position,
                    'decimal_places' => $this->currency->decimal_places,
                    'decimal_separator' => $this->currency->decimal_separator,
                    'thousand_separator' => $this->currency->thousand_separator,
                ];
            }),
            'salesperson' => $this->whenLoaded('salesperson'),
            'customer' => $this->whenLoaded('customer'),
        ];
    }
}
