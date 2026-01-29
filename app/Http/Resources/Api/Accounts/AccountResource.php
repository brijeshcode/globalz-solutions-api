<?php

namespace App\Http\Resources\Api\Accounts;

use App\Helpers\CurrencyHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountResource extends JsonResource
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
            'name' => $this->name,
            'description' => $this->description,
            'is_active' => $this->is_active,
            'hide_from_transaction' => $this->hide_from_transaction,
            'include_in_total' => $this->include_in_total,
            'opening_balance' => $this->opening_balance,
            'usd_current_balance' => CurrencyHelper::toUsd($this->currency_id, $this->current_balance),
            'current_balance' => $this->current_balance,
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
            'account_type' => $this->whenLoaded('accountType', function () {
                return [
                    'id' => $this->accountType->id,
                    'name' => $this->accountType->name,
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
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
