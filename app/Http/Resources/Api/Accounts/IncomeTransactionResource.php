<?php

namespace App\Http\Resources\Api\Accounts;

use App\Http\Resources\Api\EmbeddedDocumentResource;
use Illuminate\Http\Request;
use App\Helpers\FeatureHelper;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Accounts\IncomeTransaction
 */
class IncomeTransactionResource extends JsonResource
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
            'is_synced_to_old' => $this->when(FeatureHelper::isSyncin(), fn () => $this->is_synced_to_old),
            'date' => $this->date->format('Y-m-d'),
            'code' => $this->code,
            'subject' => $this->subject,
            'amount' => $this->amount,
            'amount_usd' => $this->amount_usd,
            'order_number' => $this->order_number,
            'check_number' => $this->check_number,
            'bank_ref_number' => $this->bank_ref_number,
            'note' => $this->note,

            'income_category' => $this->whenLoaded('incomeCategory', function () {
                return [
                    'id' => $this->incomeCategory->id,
                    'name' => $this->incomeCategory->name,
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

            // Documents
            'documents' => EmbeddedDocumentResource::collection($this->whenLoaded('documents')),

            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),

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
        ];
    }
}
