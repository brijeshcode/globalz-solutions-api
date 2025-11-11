<?php

namespace App\Http\Resources\Api\Items;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PriceListItemResource extends JsonResource
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
            'price_list_id' => $this->price_list_id,
            'item_code' => $this->item_code,
            'item_id' => $this->item_id,
            'item_description' => $this->item_description,
            'sell_price' => $this->sell_price + 0,
            // 'created_at' => $this->created_at,
            // 'updated_at' => $this->updated_at,
            // 'deleted_at' => $this->deleted_at,
            // 'created_by' => $this->created_by,
            // 'updated_by' => $this->updated_by,

            // Relationships
            
            'price_list' => $this->whenLoaded('priceList', function () {
                return [
                    'id' => $this->priceList->id,
                    'code' => $this->priceList->code,
                    'description' => $this->priceList->description,
                ];
            }),
        ];
    }
}
