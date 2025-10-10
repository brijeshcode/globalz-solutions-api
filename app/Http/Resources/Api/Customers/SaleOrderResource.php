<?php

namespace App\Http\Resources\Api\Customers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleOrderResource extends JsonResource
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
            'sale_code' => $this->sale_code,
            'customer_id' => $this->customer_id,
            'salesperson_id' => $this->salesperson_id,
            'currency_id' => $this->currency_id,
            'warehouse_id' => $this->warehouse_id,
            'customer_payment_term_id' => $this->customer_payment_term_id,
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
            'note' => $this->note,

            // Approval status
            'is_approved' => $this->isApproved(),
            'is_pending' => $this->isPending(),

            // Shipping status
            'status' => $this->status,

            // Approval fields
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at,
            'approve_note' => $this->approve_note,

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
                    'mof_tax_number' => $this->when($this->customer->mof_tax_number, $this->customer->mof_tax_number),
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

            // Sale items
            'items' => $this->whenLoaded('items', function () {
                return $this->items->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'item_id' => $item->item_id,
                        // 'supplier_id' => $item->supplier_id,
                        'quantity' => $item->quantity,
                        'price' => $item->price,
                        'ttc_price' => $item->ttc_price,
                        'tax_percent' => $item->tax_percent,
                        'discount_percent' => $item->discount_percent,
                        'unit_discount_amount' => $item->unit_discount_amount,
                        'discount_amount' => $item->discount_amount,
                        'total_price' => $item->total_price,
                        'total_price_usd' => $item->total_price_usd,
                        'cost_price' => $item->cost_price,
                        'unit_profit' => $item->unit_profit,
                        'total_profit' => $item->total_profit,
                        'note' => $item->note,
                        'created_at' => $item->created_at,
                        'updated_at' => $item->updated_at,
                        'item_code' => $item->item_code,
                        // Item details
                        'item' => $item->relationLoaded('item') ? [
                            'id' => $item->item->id,
                            'name' => $item->item->short_name,
                            'code' => $item->item->code,
                            'description' => $item->item->description,
                            'unit' => $item->item->relationLoaded('itemUnit') ? [
                                'id' => $item->item->itemUnit?->id,
                                'name' => $item->item->itemUnit?->name,
                                'symbol' => $item->item->itemUnit?->symbol,
                            ] : null,
                        ] : null,
                    ];
                });
            }),
        ];
    }

}
