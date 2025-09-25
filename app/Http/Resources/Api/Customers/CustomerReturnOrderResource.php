<?php

namespace App\Http\Resources\Api\Customers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerReturnOrderResource extends JsonResource
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
            'date' => $this->date,
            'prefix' => $this->prefix,
            'code' => $this->code,
            'return_code' => $this->return_code,
            'customer_id' => $this->customer_id,
            'salesperson_id' => $this->salesperson_id,
            'currency_id' => $this->currency_id,
            'warehouse_id' => $this->warehouse_id,
            'total' => $this->total,
            'total_usd' => $this->total_usd,
            'total_volume_cbm' => $this->total_volume_cbm,
            'total_weight_kg' => $this->total_weight_kg,
            'note' => $this->note,
            'currency_rate' => $this->currency_rate,

            // Status fields
            'is_approved' => $this->isApproved(),
            'is_pending' => $this->isPending(),
            'is_received' => $this->isReceived(),
            'status' => $this->getStatusAttribute(),

            // Approval fields
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at,
            'approve_note' => $this->approve_note,

            // Return received fields
            'return_received_by' => $this->return_received_by,
            'return_received_at' => $this->return_received_at,
            'return_received_note' => $this->return_received_note,

            // Timestamps
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,

            // Relationships
            'customer' => $this->whenLoaded('customer', function () {
                return [
                    'id' => $this->customer->id,
                    'name' => $this->customer->name,
                    'code' => $this->customer->code,
                    'address' => $this->when($this->customer->address, $this->customer->address),
                    'city' => $this->when($this->customer->city, $this->customer->city),
                    'mobile' => $this->when($this->customer->mobile, $this->customer->mobile),
                ];
            }),

            'currency' => $this->whenLoaded('currency', function () {
                return [
                    'id' => $this->currency->id,
                    'name' => $this->currency->name,
                    'code' => $this->currency->code,
                    'symbol' => $this->when($this->currency->symbol, $this->currency->symbol),
                ];
            }),

            'warehouse' => $this->whenLoaded('warehouse', function () {
                return [
                    'id' => $this->warehouse->id,
                    'name' => $this->warehouse->name,
                    'address' => $this->when($this->warehouse->address_line_1, $this->warehouse->address_line_1),
                ];
            }),

            'salesperson' => $this->whenLoaded('salesperson', function () {
                return [
                    'id' => $this->salesperson->id,
                    'name' => $this->salesperson->name,
                ];
            }),

            'approved_by_user' => $this->whenLoaded('approvedBy', function () {
                return [
                    'id' => $this->approvedBy->id,
                    'name' => $this->approvedBy->name,
                ];
            }),

            'return_received_by_user' => $this->whenLoaded('returnReceivedBy', function () {
                return [
                    'id' => $this->returnReceivedBy->id,
                    'name' => $this->returnReceivedBy->name,
                ];
            }),

            'created_by_user' => $this->whenLoaded('createdBy', function () {
                return [
                    'id' => $this->createdBy->id,
                    'name' => $this->createdBy->name,
                ];
            }),

            'updated_by_user' => $this->whenLoaded('updatedBy', function () {
                return [
                    'id' => $this->updatedBy->id,
                    'name' => $this->updatedBy->name,
                ];
            }),

            // Return items
            'items' => $this->whenLoaded('items', function () {
                return $this->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'item_code' => $item->item_code,
                        'item_id' => $item->item_id,
                        'quantity' => $item->quantity,
                        'price' => $item->price,
                        'discount_percent' => $item->discount_percent,
                        'unit_discount_amount' => $item->unit_discount_amount,
                        'discount_amount' => $item->discount_amount,
                        'tax_percent' => $item->tax_percent,
                        'ttc_price' => $item->ttc_price,
                        'total_price' => $item->total_price,
                        'total_price_usd' => $item->total_price_usd,
                        'total_volume_cbm' => $item->total_volume_cbm,
                        'total_weight_kg' => $item->total_weight_kg,
                        'note' => $item->note,
                        'created_at' => $item->created_at,
                        'updated_at' => $item->updated_at,

                        // Item details
                        'item' => $item->relationLoaded('item') ? [
                            'id' => $item->item->id,
                            'name' => $item->item->short_name,
                            'code' => $item->item->code,
                            'unit' => [
                                'name' => $item->item?->itemUnit?->name,
                                'short_name' => $item->item?->itemUnit?->short_name
                            ]

                        ] : null,
                    ];
                });
            }),
        ];
    }

    private function getStatusAttribute(): string
    {
        if ($this->isReceived()) {
            return 'received';
        }

        if ($this->isApproved()) {
            return 'approved';
        }

        return 'pending';
    }
}
