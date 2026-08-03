<?php

namespace App\Http\Resources\Api\Vehicle;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CarResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'name'         => $this->name,
            'plate_number' => $this->plate_number,
            'year'         => $this->year,
            'color'        => $this->color,
            'make'         => $this->make,
            'model'        => $this->model,
            'note'         => $this->note,
            'is_active'    => $this->is_active,
            'created_by'   => ['id' => $this->createdBy?->id, 'name' => $this->createdBy?->name],
            'updated_by'   => ['id' => $this->updatedBy?->id, 'name' => $this->updatedBy?->name],
            'created_at'   => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at'   => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
