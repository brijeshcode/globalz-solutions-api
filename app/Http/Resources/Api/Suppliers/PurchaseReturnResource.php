<?php

namespace App\Http\Resources\Api\Suppliers;

use App\Http\Resources\Api\EmbeddedDocumentResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Suppliers\PurchaseReturn
 */
class PurchaseReturnResource extends JsonResource
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
            'code' => $this->code,
            'prefix' => $this->prefix,
            'date' => $this->date->format('Y-m-d'),
            'shipping_status' => $this->shipping_status,
            'supplier_purchase_return_number' => $this->supplier_purchase_return_number,
            'currency_rate' => number_format($this->currency_rate, 6, '.', ''),

            // Financial fields
            'shipping_fee_usd' => number_format($this->shipping_fee_usd, 4, '.', ''),
            'customs_fee_usd' => number_format($this->customs_fee_usd, 4, '.', ''),
            'other_fee_usd' => number_format($this->other_fee_usd, 4, '.', ''),
            'tax_usd' => number_format($this->tax_usd, 4, '.', ''),
            'shipping_fee_usd_percent' => number_format($this->shipping_fee_usd_percent, 2, '.', ''),
            'customs_fee_usd_percent' => number_format($this->customs_fee_usd_percent, 2, '.', ''),
            'other_fee_usd_percent' => number_format($this->other_fee_usd_percent, 2, '.', ''),
            'tax_usd_percent' => number_format($this->tax_usd_percent, 2, '.', ''),
            'sub_total' => number_format($this->sub_total, 4, '.', ''),
            'sub_total_usd' => number_format($this->sub_total_usd, 4, '.', ''),
            'additional_charge_amount' => number_format($this->additional_charge_amount, 4, '.', ''),
            'additional_charge_amount_usd' => number_format($this->additional_charge_amount_usd, 4, '.', ''),
            'total' => number_format($this->total, 4, '.', ''),
            'total_usd' => number_format($this->total_usd, 4, '.', ''),
            'final_total' => number_format($this->final_total, 4, '.', ''),
            'final_total_usd' => number_format($this->final_total_usd, 4, '.', ''),

            // Computed attributes
            'total_items_count' => $this->total_items_count,
            'total_quantity' => $this->total_quantity,
            'has_items' => $this->has_items,

            'note' => $this->note,
            'supplier_id' => $this->supplier_id,

            // Relationships
            'supplier' => $this->whenLoaded('supplier', function () {
                return [
                    'id' => $this->supplier->id,
                    'code' => $this->supplier->code,
                    'name' => $this->supplier->name,
                    'email' => $this->when(filled($this->supplier->email), $this->supplier->email),
                    'phone' => $this->when(filled($this->supplier->phone), $this->supplier->phone),
                    'address' => $this->when(filled($this->supplier->address), $this->supplier->address),
                ];
            }),

            'warehouse_id' => $this->warehouse_id,
            'warehouse' => $this->whenLoaded('warehouse', function () {
                return [
                    'id' => $this->warehouse->id,
                    'name' => $this->warehouse->name,
                ];
            }),

            'currency_id' => $this->currency_id,
            'currency' => $this->whenLoaded('currency', function () {
                return [
                    'id' => $this->currency->id,
                    'name' => $this->currency->name,
                    'code' => $this->currency->code,
                    'symbol' => $this->when(filled($this->currency->symbol), $this->currency->symbol),
                    'calculation_type' => $this->currency->calculation_type,
                    'symbol_position' => $this->currency->symbol_position,
                    'decimal_places' => $this->currency->decimal_places,
                    'decimal_separator' => $this->currency->decimal_separator,
                    'thousand_separator' => $this->currency->thousand_separator,
                ];
            }),

            'items' => $this->whenLoaded('purchaseReturnItems', function () {
                return $this->purchaseReturnItems->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'item_id' => $item->item_id,
                        'item_code' => $item->item_code,
                        'price' => $item->price,
                        'quantity' => $item->quantity,
                        'discount_percent' => $item->discount_percent,
                        'unit_discount_amount' => $item->unit_discount_amount,
                        'discount_amount' => $item->discount_amount,
                        'total_price' => $item->total_price,
                        'total_price_usd' => $item->total_price_usd,
                        'total_shipping_usd' => $item->total_shipping_usd,
                        'total_customs_usd' => $item->total_customs_usd,
                        'total_other_usd' => $item->total_other_usd,
                        'final_total_cost_usd' => $item->final_total_cost_usd,
                        'cost_per_item_usd' => $item->cost_per_item_usd,
                        'note' => $item->note,

                        // Computed attributes
                        'net_price' => $item->net_price,
                        'has_discount' => $item->has_discount,
                        'unit_cost_usd' => $item->unit_cost_usd,

                        'item' => $this->when($item->relationLoaded('item'), function () use ($item) {
                            return [
                                'id' => $item->item->id,
                                'code' => $item->item->code,
                                'name' => $item->item->short_name,
                                'unit' => $item->item->unit ?? null,
                            ];
                        }),
                    ];
                });
            }),

            'documents' => EmbeddedDocumentResource::collection($this->whenLoaded('documents')),

            // Audit fields
            'created_by' => $this->whenLoaded('createdBy', function () {
                return [
                    'id' => $this->createdBy->id,
                    'name' => $this->createdBy->name,
                ];
            }),

            'updated_by' => $this->whenLoaded('updatedBy', function () {
                return [
                    'id' => $this->updatedBy->id,
                    'name' => $this->updatedBy->name,
                ];
            }),

            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
