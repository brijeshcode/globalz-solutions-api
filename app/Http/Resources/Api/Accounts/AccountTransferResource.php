<?php

namespace App\Http\Resources\Api\Accounts;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountTransferResource extends JsonResource
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
            'date' => $this->date?->format('Y-m-d H:i:s'),
            'code' => $this->code,
            'prefix' => $this->prefix,
            'from_account_id' => $this->from_account_id,
            'to_account_id' => $this->to_account_id,
            'from_currency_id' => $this->from_currency_id,
            'to_currency_id' => $this->to_currency_id,
            'received_amount' => $this->received_amount,
            'sent_amount' => $this->sent_amount,
            'currency_rate' => $this->currency_rate,
            'note' => $this->note,
            'from_account' => $this->whenLoaded('fromAccount', function () {
                return [
                    'id' => $this->fromAccount->id,
                    'name' => $this->fromAccount->name,
                    'account_type_id' => $this->fromAccount->account_type_id,
                    'currency_id' => $this->fromAccount->currency_id,
                    'current_balance' => $this->when(
                        isset($this->fromAccount->current_balance),
                        $this->fromAccount->current_balance
                    ),
                ];
            }),
            'to_account' => $this->whenLoaded('toAccount', function () {
                return [
                    'id' => $this->toAccount->id,
                    'name' => $this->toAccount->name,
                    'account_type_id' => $this->toAccount->account_type_id,
                    'currency_id' => $this->toAccount->currency_id,
                    'current_balance' => $this->when(
                        isset($this->toAccount->current_balance),
                        $this->toAccount->current_balance
                    ),
                ];
            }),
            'from_currency' => $this->whenLoaded('fromCurrency', function () {
                return [
                    'id' => $this->fromCurrency->id,
                    'name' => $this->fromCurrency->name,
                    'code' => $this->fromCurrency->code,
                    'symbol' => $this->when($this->fromCurrency->symbol, $this->fromCurrency->symbol),
                    'symbol_position' => $this->fromCurrency->symbol_position,
                    'decimal_places' => $this->fromCurrency->decimal_places,
                    'decimal_separator' => $this->fromCurrency->decimal_separator,
                    'thousand_separator' => $this->fromCurrency->thousand_separator,
                    'calculation_type' => $this->fromCurrency->calculation_type,
                ];
            }),
            'to_currency' => $this->whenLoaded('toCurrency', function () {
                return [
                    'id' => $this->toCurrency->id,
                    'name' => $this->toCurrency->name,
                    'code' => $this->toCurrency->code,
                    'symbol' => $this->when($this->toCurrency->symbol, $this->toCurrency->symbol),
                    'symbol_position' => $this->toCurrency->symbol_position,
                    'decimal_places' => $this->toCurrency->decimal_places,
                    'decimal_separator' => $this->toCurrency->decimal_separator,
                    'thousand_separator' => $this->toCurrency->thousand_separator,
                    'calculation_type' => $this->toCurrency->calculation_type,
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
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s')
        ];
    }
}
