<?php

namespace App\Http\Resources\Api\Suppliers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

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
            'date' => $this->date?->format('Y-m-d'),
            'shipping_status' => $this->shipping_status,
            'supplier_purchase_return_number' => $this->supplier_purchase_return_number,
            'currency_rate' => $this->currency_rate,

            // Financial fields
            'shipping_fee_usd' => $this->shipping_fee_usd,
            'customs_fee_usd' => $this->customs_fee_usd,
            'other_fee_usd' => $this->other_fee_usd,
            'tax_usd' => $this->tax_usd,
            'shipping_fee_usd_percent' => $this->shipping_fee_usd_percent,
            'customs_fee_usd_percent' => $this->customs_fee_usd_percent,
            'other_fee_usd_percent' => $this->other_fee_usd_percent,
            'tax_usd_percent' => $this->tax_usd_percent,
            'sub_total' => $this->sub_total,
            'sub_total_usd' => $this->sub_total_usd,
            'additional_charge_amount' => $this->additional_charge_amount,
            'additional_charge_amount_usd' => $this->additional_charge_amount_usd,
            'total' => $this->total,
            'total_usd' => $this->total_usd,
            'final_total' => $this->final_total,
            'final_total_usd' => $this->final_total_usd,

            // Computed attributes
            'total_items_count' => $this->getTotalItemsCountAttribute(),
            'total_quantity' => $this->getTotalQuantityAttribute(),
            'has_items' => $this->getHasItemsAttribute(),

            'note' => $this->note,
            'supplier_id' => $this->supplier_id,

            // Relationships
            'supplier' => $this->whenLoaded('supplier', function () {
                return [
                    'id' => $this->supplier->id,
                    'code' => $this->supplier->code,
                    'name' => $this->supplier->name,
                    'email' => $this->when($this->supplier->email, $this->supplier->email),
                    'phone' => $this->when($this->supplier->phone, $this->supplier->phone),
                    'address' => $this->when($this->supplier->address, $this->supplier->address),
                ];
            }),

            'warehouse_id' => $this->warehouse_id,
            'warehouse' => $this->whenLoaded('warehouse', function () {
                return [
                    'id' => $this->warehouse->id,
                    'name' => $this->warehouse->name,
                    'address' => $this->when($this->warehouse->address, $this->warehouse->address),
                ];
            }),

            'currency_id' => $this->currency_id,
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
                        'net_price' => $item->getNetPriceAttribute(),
                        'has_discount' => $item->getHasDiscountAttribute(),
                        'unit_cost_usd' => $item->getUnitCostUsdAttribute(),

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

            'documents' => $this->whenLoaded('documents', function () {
                return $this->documents->map(function ($doc) {
                    return [
                        'id' => $doc->id,
                        'original_name' => $doc->original_name,
                        'file_name' => $doc->file_name,
                        'file_size' => $doc->file_size,
                        'file_size_human' => $doc->file_size_human ?? null,
                        'thumbnail_url' => $doc->thumbnail_url ?? null,
                        'download_url' => $doc->download_url ?? null,
                        'uploaded_at' => $doc->created_at?->format('Y-m-d H:i:s'),
                    ];
                });
            }),

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
