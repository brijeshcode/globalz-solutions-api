<?php

namespace App\Http\Resources\Api\Setups\Generals\Currencies;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class currencyRateResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'currency_id' => $this->currency_id,
            'rate' => $this->rate,
            'currency' => $this->whenLoaded('currency', function () {
                return [
                    'id' => $this->currency->id,
                    'name' => $this->currency->name,
                ];
            }),
            
            'created_by' => [
                'id' => $this->createdBy?->id,
                'name' => $this->createdBy?->name,
            ],
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
        ];
    }
}
