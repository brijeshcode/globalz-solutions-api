<?php

namespace App\Http\Resources\Api\Setups\Vehicle;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CarRefillResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $kmDriven = (float) $this->km_driven;
        $amount   = (float) $this->amount;
        $kmCost   = ($kmDriven > 0) ? round($amount / $kmDriven, 4) : null;

        return [
            'id'             => $this->id,
            'date'           => $this->date?->format('Y-m-d H:i:s'),
            'code'           => $this->code,
            'car_id'         => $this->car_id,
            'gas_station_id' => $this->gas_station_id,
            'driver_id'      => $this->driver_id,
            'odometer'       => $this->odometer,
            'km_driven'      => $this->km_driven,
            'amount'         => $this->amount,
            'amount_usd'     => $this->amount_usd,
            'currency_id'    => $this->currency_id,
            'currency_rate'  => $this->currency_rate,
            'km_cost'        => $kmCost,
            'invoices_count' => $this->invoices_count,
            'note'           => $this->note,
            'car' => $this->whenLoaded('car', fn() => [
                'id'   => $this->car->id,
                'name' => $this->car->name,
            ]),
            'gas_station' => $this->whenLoaded('gasStation', fn() => [
                'id'   => $this->gasStation->id,
                'name' => $this->gasStation->name,
            ]),
            'driver' => $this->whenLoaded('driver', fn() => [
                'id'   => $this->driver->id,
                'name' => $this->driver->name,
            ]),
            'created_by' => ['id' => $this->createdBy?->id, 'name' => $this->createdBy?->name],
            'updated_by' => ['id' => $this->updatedBy?->id, 'name' => $this->updatedBy?->name],
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
