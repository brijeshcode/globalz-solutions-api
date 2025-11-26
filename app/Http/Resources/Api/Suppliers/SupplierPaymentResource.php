<?php

namespace App\Http\Resources\Api\Suppliers;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SupplierPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'date' => $this->date,
            'prefix' => $this->prefix,
            'code' => $this->code,
            'payment_code' => $this->payment_code,
            'supplier_id' => $this->supplier_id,
            'supplier_payment_term_id' => $this->supplier_payment_term_id,
            'account_id' => $this->account_id,
            'currency_id' => $this->currency_id,
            'currency_rate' => $this->currency_rate,
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
                    'address' => $this->when($this->supplier->address, $this->supplier->address),
                    'phone' => $this->when($this->supplier->phone, $this->supplier->phone),
                    'mobile' => $this->when($this->supplier->mobile, $this->supplier->mobile),
                    'email' => $this->when($this->supplier->email, $this->supplier->email),
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

            // Documents
            'documents' => $this->whenLoaded('documents', function () {
                return $this->documents->map(function ($document) {
                    return [
                        'id' => $document->id,
                        'documentable_type' => $document->documentable_type,
                        'file_name' => $document->file_name,
                        'file_size' => $document->file_size,
                        'documentable_id' => $document->documentable_id,
                        // Appended attributes from Document model
                        'thumbnail_url' => $document->thumbnail_url,
                        'download_url' => $document->download_url,
                        'preview_url' => $document->preview_url,
                         
                    ];
                });
            }),
        ];
    }
}
