<?php

namespace App\Http\Resources\Api\Customers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerCreditDebitNoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'date' => $this->date,
            'prefix' => $this->prefix,
            'type' => $this->type,
            'code' => $this->code,
            'note_code' => $this->note_code,
            'customer_id' => $this->customer_id,
            'currency_id' => $this->currency_id,
            'currency_rate' => $this->currency_rate,
            'amount' => $this->amount,
            'amount_usd' => $this->amount_usd,
            'note' => $this->note,
            'is_credit' => $this->isCredit(),
            'is_debit' => $this->isDebit(),
            'type_label' => ucfirst($this->type),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,

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
        ];
    }
}
