<?php

namespace App\Http\Resources\Api\Setups\Generals\Accounts;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Setups\Generals\Accounts\Asset
 */
class AssetsResource extends JsonResource
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
            'date' => $this->date?->format('Y-m-d'),
            'quantity' => $this->quantity,
            'currency_id' => $this->currency_id,
            'currency' => $this->whenLoaded('currency', fn () => [
                'id' => $this->currency?->id,
                'name' => $this->currency?->name,
                'code' => $this->currency?->code,
            ]),
            'currency_rate' => $this->currency_rate,
            'amount' => $this->amount,
            'amount_usd' => $this->amount_usd,
            'note' => $this->note,
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
