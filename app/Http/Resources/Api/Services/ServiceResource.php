<?php

namespace App\Http\Resources\Api\Services;

use App\Helpers\FeatureHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Services\Services
 */
class ServiceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'hsn' => $this->when(FeatureHelper::isHsnField(), $this->hsn),
            'tax_code_id' => $this->tax_code_id,
            'amount' => $this->amount + 0,
            'amount_usd' => $this->amount_usd + 0,
            'currency_id' => $this->currency_id,
            'currency_rate' => $this->currency_rate + 0,
            'notes' => $this->notes,
            'is_active' => $this->is_active,
            'tax_code' => $this->whenLoaded('taxCode'),
            'currency' => $this->whenLoaded('currency'),
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
