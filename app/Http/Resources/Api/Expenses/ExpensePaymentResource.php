<?php

namespace App\Http\Resources\Api\Expenses;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ExpensePaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'              => $this->id,
            'code'            => $this->code,
            'prefix'          => $this->prefix,
            'date'            => $this->date?->format('Y-m-d'),
            'amount'          => $this->amount,
            'amount_usd'      => $this->amount_usd,
            'note'            => $this->note,
            'order_number'    => $this->order_number,
            'check_number'    => $this->check_number,
            'bank_ref_number' => $this->bank_ref_number,

            'expense_transaction' => $this->whenLoaded('expenseTransaction', fn () => [
                'id'      => $this->expenseTransaction->id,
                'code'    => $this->expenseTransaction->code,
                'subject' => $this->expenseTransaction->subject,
                'amount'  => $this->expenseTransaction->amount,
            ]),

            'account' => $this->whenLoaded('account', fn () => [
                'id'   => $this->account->id,
                'name' => $this->account->name,
            ]),

            'currency' => $this->whenLoaded('currency', fn () => [
                'id'                => $this->currency->id,
                'name'              => $this->currency->name,
                'code'              => $this->currency->code,
                'symbol'            => $this->currency->symbol,
                'decimal_places'    => $this->currency->decimal_places,
                'decimal_separator' => $this->currency->decimal_separator,
                'thousand_separator'=> $this->currency->thousand_separator,
            ]),

            'created_by' => [
                'id'   => $this->createdBy?->id,
                'name' => $this->createdBy?->name,
            ],
            'updated_by' => [
                'id'   => $this->updatedBy?->id,
                'name' => $this->updatedBy?->name,
            ],

            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
