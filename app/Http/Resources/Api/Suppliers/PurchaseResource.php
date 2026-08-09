<?php

namespace App\Http\Resources\Api\Suppliers;

use App\Http\Resources\Api\EmbeddedDocumentResource;
use App\Models\Suppliers\PurchaseExpense;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Suppliers\Purchase
 */
class PurchaseResource extends JsonResource
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
            'supplier_invoice_number' => $this->supplier_invoice_number,
            'currency_rate' => number_format($this->currency_rate, 6, '.', '') ,
            
            // Financial fields
            'sub_total' => number_format($this->sub_total, 4 ,'.', ''),
            'sub_total_usd' => number_format($this->sub_total_usd, 4 ,'.', ''),
            'discount_amount' => number_format($this->discount_amount, 4, '.', ''),
            'discount_amount_usd' => number_format($this->discount_amount_usd, 4, '.', ''),
            'total' => number_format($this->total, 4, '.', ''),
            'total_usd' => number_format($this->total_usd, 4, '.', ''),
            'tax_usd' => number_format($this->tax_usd, 4, '.', ''),
            'tax_usd_percent' => number_format($this->tax_usd_percent, 2, '.', ''),
            'total_expense_usd' => number_format($this->total_expense_usd, 4, '.', ''),
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
            
            // remove this completealty if we find no error in the system
            // 'account_id' => $this->account_id,
            // 'account' => $this->whenLoaded('account', function () {
            //     return [
            //         'id' => $this->account->id,
            //         'name' => $this->account->name,
            //         'type' => $this->when($this->account->type, $this->account->type),
            //     ];
            // }),
            
            'items' => $this->whenLoaded('purchaseItems', function () {
                return $this->purchaseItems->map(function ($item) {
                    return [
                        'id' => $item->id,
                        'item_id' => $item->item_id,
                        'item_code' => $item->item_code,
                        'price' => number_format($item->price, 5, '.', ''),
                        'quantity' => $item->quantity,
                        'discount_percent' => $item->discount_percent,
                        'discount_amount' => number_format($item->discount_amount, 5, '.', ''),
                        'total_price' => number_format($item->total_price, 5, '.', ''),
                        'total_price_usd' => number_format($item->total_price_usd, 2, '.', '') ,
                        'total_expense_usd' => number_format($item->total_expense_usd, 2, ''),
                        'final_total_cost_usd' => number_format($item->final_total_cost_usd, 2, ''),
                        'cost_per_item_usd' => number_format($item->cost_per_item_usd, 4, ''),
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
                        
                        // 'created_at' => $item->created_at?->format('Y-m-d H:i:s'),
                        // 'updated_at' => $item->updated_at?->format('Y-m-d H:i:s'),
                    ];
                });
            }),
            
            'purchase_expenses' => $this->whenLoaded('purchaseExpenses', function () {
                return $this->purchaseExpenses->map(fn (PurchaseExpense $pe) => $this->formatPurchaseExpense($pe));
            }),

            // Shipping status
            'status' => $this->status,
            
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

    /**
     * @return array<string, mixed>
     */
    private function formatPurchaseExpense(PurchaseExpense $pe): array
    {
        $tx = $pe->expenseTransaction;

        return [
            'id'                     => $pe->id,
            'exclude_from_item_cost' => $pe->exclude_from_item_cost,
            'expense_transaction'    => $tx ? [
                'id'                   => $tx->id,
                'amount'               => $tx->amount,
                'amount_usd'           => $tx->amount_usd,
                'currency_id'          => $tx->currency_id,
                'currency_rate'        => $tx->currency_rate,
                'account_id'           => $tx->account_id,
                'account'              => $tx->relationLoaded('account') && $tx->account ? [
                    'id'   => $tx->account->id,
                    'name' => $tx->account->name,
                ] : null,
                'date'                 => $tx->date->format('Y-m-d'),
                'payment_date'         => $tx->relationLoaded('payments') ? $tx->payments->first()?->date?->format('Y-m-d') : null,
                'note'                 => $tx->note,
                'payment_note'                 => $tx->note,
                'payment_status'       => $tx->payment_status,
                'is_paid'              => $tx->payment_status === 'paid',
                'vat_amount'                  => $tx->vat_amount,
                'vat_amount_usd'                  => $tx->vat_amount_usd,
                'expense_category'     => $tx->relationLoaded('expenseCategory') && $tx->expenseCategory ? [
                    'id'                  => $tx->expenseCategory->id,
                    'parent_id'           => $tx->expenseCategory->parent_id,
                    'name'                => $tx->expenseCategory->name,
                    'description'         => $tx->expenseCategory->description,
                    'is_active'           => $tx->expenseCategory->is_active,
                    'exclude_from_profit' => $tx->expenseCategory->exclude_from_profit,
                    'is_vat_category'     => $tx->expenseCategory->is_vat_category,
                    'is_system'           => $tx->expenseCategory->is_system,
                ] : null,
            ] : null,
        ];
    }
}
