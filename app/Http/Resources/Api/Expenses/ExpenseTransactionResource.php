<?php

namespace App\Http\Resources\Api\Expenses;

use App\Http\Resources\Api\EmbeddedDocumentResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Expenses\ExpenseTransaction
 */
class ExpenseTransactionResource extends JsonResource
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
            'date' => $this->date->format('Y-m-d'),
            'expense_month' => $this->expense_month?->format('Y-m'),
            'code' => $this->code,
            'subject' => $this->subject,
            'amount'         => $this->amount,
            'paid_amount'     => $this->paid_amount,
            'paid_amount_usd' => $this->paid_amount_usd,
            'due_amount'      => $this->due_amount,
            'payment_status'  => $this->payment_status,
            'currency_rate'   => number_format($this->currency_rate, 4, '.', ''),
            'amount_usd'      => $this->amount_usd,
            'vat_amount'       => $this->vat_amount,
            'vat_amount_usd'   => $this->vat_amount_usd,
            'total_amount'     => $this->total_amount,
            'total_amount_usd' => $this->total_amount_usd,
            'order_number' => $this->order_number,
            'check_number' => $this->check_number,
            'bank_ref_number' => $this->bank_ref_number,
            'note' => $this->note,

            'expense_category' => $this->whenLoaded('expenseCategory', function () {
                return [
                    'id' => $this->expenseCategory->id,
                    'name' => $this->expenseCategory->name,
                ];
            }),

            'account' => $this->whenLoaded('account', function () {
                return [
                    'id' => $this->account->id,
                    'name' => $this->account->name,
                ];
            }),

            'created_by' => [
                'id' => $this->createdBy?->id,
                'name' => $this->createdBy?->name,
            ],
            'updated_by' => [
                'id' => $this->updatedBy?->id,
                'name' => $this->updatedBy?->name,
            ],

            // Payments
            'payments' => $this->whenLoaded('payments', function () {
                return $this->payments->map(fn ($p) => [
                    'id'              => $p->id,
                    'code'            => $p->code,
                    'prefix'          => $p->prefix,
                    'date'            => $p->date?->format('Y-m-d'),
                    'amount'          => $p->amount,
                    'amount_usd'      => $p->amount_usd,
                    'note'            => $p->note,
                    'order_number'    => $p->order_number,
                    'check_number'    => $p->check_number,
                    'bank_ref_number' => $p->bank_ref_number,
                    'account'         => $p->account ? ['id' => $p->account->id, 'name' => $p->account->name] : null,
                    'created_at'      => $p->created_at?->format('Y-m-d H:i:s'),
                ]);
            }),

            // Documents
            'documents' => EmbeddedDocumentResource::collection($this->whenLoaded('documents')),

            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
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
        ];
    }
}
