<?php

namespace App\Http\Resources\Api\Setups;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CurrencyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'symbol' => $this->symbol,
            'symbol_position' => $this->symbol_position,
            'decimal_places' => $this->decimal_places,
            'decimal_separator' => $this->decimal_separator,
            'thousand_separator' => $this->thousand_separator,
            'calculation_type' => $this->calculation_type,
            'is_active' => $this->is_active,
            'display_name' => $this->display_name,
            'current_rate' => $this->getCurrentRate(),
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