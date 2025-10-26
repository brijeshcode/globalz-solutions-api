<?php

namespace App\Http\Resources\Api\Customers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CustomerPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'date' => $this->date,
            'prefix' => $this->prefix,
            'code' => $this->code,
            'payment_code' => $this->payment_code,
            'customer_id' => $this->customer_id,
            'customer_payment_term_id' => $this->customer_payment_term_id,
            'currency_id' => $this->currency_id,
            'currency_rate' => $this->currency_rate,
            'amount' => $this->amount,
            'amount_usd' => $this->amount_usd,
            'credit_limit' => $this->credit_limit,
            'last_payment_amount' => $this->last_payment_amount,
            'rtc_book_number' => $this->rtc_book_number,
            'note' => $this->note,
            'approved_by' => $this->approved_by,
            'approved_at' => $this->approved_at,
            'account_id' => $this->account_id,
            'approve_note' => $this->approve_note,
            'is_approved' => $this->isApproved(),
            'is_pending' => $this->isPending(),
            'status' => $this->isApproved() ? 'approved' : 'pending',
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
                    'salesperson' => $this->when($this->customer->relationLoaded('salesperson') && $this->customer->salesperson, function () {
                        return [
                            'id' => $this->customer->salesperson->id,
                            'name' => $this->customer->salesperson->name,
                        ];
                    }),
                ];
            }),

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

            'customer_payment_term' => $this->whenLoaded('customerPaymentTerm', function () {
                return [
                    'id' => $this->customerPaymentTerm->id,
                    'name' => $this->customerPaymentTerm->name,
                    'days' => $this->customerPaymentTerm->days,
                ];
            }),

            'approved_by_user' => $this->whenLoaded('approvedBy', function () {
                return [
                    'id' => $this->approvedBy->id,
                    'name' => $this->approvedBy->name,
                ];
            }),

            'account' => $this->whenLoaded('account', function () {
                return [
                    'id' => $this->account->id,
                    'name' => $this->account->name,
                    'code' => $this->when($this->account->code, $this->account->code),
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
