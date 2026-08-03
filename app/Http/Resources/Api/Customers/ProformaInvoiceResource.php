<?php

namespace App\Http\Resources\Api\Customers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProformaInvoiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                       => $this->id,
            'code'                     => $this->code,
            'proforma_code'            => $this->proforma_code,
            'date'                     => $this->date,
            'prefix'                   => $this->prefix,
            'status'                   => $this->status,
            'salesperson_id'           => $this->salesperson_id,
            'price_list_id'            => $this->price_list_id,
            'customer_id'              => $this->customer_id,
            'currency_id'              => $this->currency_id,
            'warehouse_id'             => $this->warehouse_id,
            'customer_payment_term_id' => $this->customer_payment_term_id,
            'client_po_number'         => $this->client_po_number,
            'currency_rate'            => $this->currency_rate + 0,
            'sub_total'                => $this->sub_total + 0,
            'sub_total_usd'            => $this->sub_total_usd + 0,
            'discount_amount'          => $this->discount_amount + 0,
            'discount_amount_usd'      => $this->discount_amount_usd + 0,
            'total'                    => $this->total + 0,
            'total_usd'                => $this->total_usd + 0,
            'total_profit'             => $this->total_profit + 0,
            'value_date'               => $this->value_date,
            'total_volume_cbm'         => $this->total_volume_cbm + 0,
            'total_weight_kg'          => $this->total_weight_kg + 0,
            'total_tax_amount'         => $this->total_tax_amount + 0,
            'total_tax_amount_usd'     => $this->total_tax_amount_usd + 0,
            'local_curreny_rate'       => $this->local_curreny_rate + 0,
            'invoice_tax_label'        => $this->invoice_tax_label,
            'invoice_nb1'              => $this->invoice_nb1,
            'invoice_nb2'              => $this->invoice_nb2,
            'note'                     => $this->note,
            'is_converted'             => $this->isConverted(),
            'converted_at'             => $this->converted_at,
            'converted_sale_id'        => $this->converted_sale_id,
            'approved_at'              => $this->approved_at,
            'created_at'               => $this->created_at,
            'updated_at'               => $this->updated_at,
            'created_by'               => $this->created_by,
            'updated_by'               => $this->updated_by,

            'status_histories' => $this->when(
                $this->relationLoaded('statusHistories'),
                fn() => $this->statusHistories->map(fn($h) => [
                    'status'     => $h->status,
                    'changed_at' => $h->created_at,
                    'changed_by' => $h->relationLoaded('changedBy') && $h->changedBy
                        ? ['id' => $h->changedBy->id, 'name' => $h->changedBy->name]
                        : null,
                ])
            ),

            'items'     => ProformaInvoiceItemResource::collection($this->whenLoaded('items')),
            'warehouse' => $this->whenLoaded('warehouse'),
            'priceList' => $this->whenLoaded('priceList'),
            'currency'  => $this->whenLoaded('currency', function () {
                return [
                    'id'                 => $this->currency->id,
                    'name'               => $this->currency->name,
                    'code'               => $this->currency->code,
                    'symbol'             => $this->currency->symbol,
                    'calculation_type'   => $this->currency->calculation_type,
                    'symbol_position'    => $this->currency->symbol_position,
                    'decimal_places'     => $this->currency->decimal_places,
                    'decimal_separator'  => $this->currency->decimal_separator,
                    'thousand_separator' => $this->currency->thousand_separator,
                ];
            }),
            'salesperson'      => $this->whenLoaded('salesperson'),
            'customer'         => $this->whenLoaded('customer'),
            'approved_by_user' => $this->whenLoaded('approvedBy', fn() => $this->approvedBy
                ? ['id' => $this->approvedBy->id, 'name' => $this->approvedBy->name]
                : null),
            'created_by_user'  => $this->whenLoaded('createdBy', fn() => $this->createdBy
                ? ['id' => $this->createdBy->id, 'name' => $this->createdBy->name]
                : null),
            'updated_by_user'  => $this->whenLoaded('updatedBy', fn() => $this->updatedBy
                ? ['id' => $this->updatedBy->id, 'name' => $this->updatedBy->name]
                : null),
        ];
    }
}
