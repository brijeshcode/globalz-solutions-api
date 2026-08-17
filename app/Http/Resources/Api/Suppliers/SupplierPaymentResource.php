<?php

namespace App\Http\Resources\Api\Suppliers;

use App\Http\Resources\Api\EmbeddedDocumentResource;
use Illuminate\Http\Request;
use App\Helpers\FeatureHelper;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Suppliers\SupplierPayment
 */
class SupplierPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'is_synced_to_old' => $this->when(FeatureHelper::isSyncin(), fn () => $this->is_synced_to_old),
            'date' => $this->date,
            'prefix' => $this->prefix,
            'code' => $this->code,
            'payment_code' => $this->payment_code,
            'supplier_id' => $this->supplier_id,
            'supplier_payment_term_id' => $this->supplier_payment_term_id,
            'account_id' => $this->account_id,
            'currency_id' => $this->currency_id,
            'currency_rate' => number_format($this->currency_rate, 4, '.', ''),
            'amount' => $this->amount,
            'amount_usd' => $this->amount_usd,
            'last_payment_amount_usd' => $this->last_payment_amount_usd,
            'supplier_order_number' => $this->supplier_order_number,
            'check_number' => $this->check_number,
            'bank_ref_number' => $this->bank_ref_number,
            'note' => $this->note,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'deleted_at' => $this->deleted_at,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,

            'supplier' => $this->whenLoaded('supplier', function () {
                return [
                    'id' => $this->supplier->id,
                    'name' => $this->supplier->name,
                    'code' => $this->supplier->code,
                    'address' => $this->when(filled($this->supplier->address), $this->supplier->address),
                    'phone' => $this->when(filled($this->supplier->phone), $this->supplier->phone),
                    'mobile' => $this->when(filled($this->supplier->mobile), $this->supplier->mobile),
                    'email' => $this->when(filled($this->supplier->email), $this->supplier->email),
                ];
            }),

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

            'supplier_payment_term' => $this->whenLoaded('supplierPaymentTerm', function () {
                return [
                    'id' => $this->supplierPaymentTerm->id,
                    'name' => $this->supplierPaymentTerm->name,
                    'days' => $this->supplierPaymentTerm->days,
                ];
            }),

            'account' => $this->whenLoaded('account', function () {
                return [
                    'id' => $this->account->id,
                    'name' => $this->account->name,
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

            // Documents
            'documents' => EmbeddedDocumentResource::collection($this->whenLoaded('documents')),
        ];
    }
}
