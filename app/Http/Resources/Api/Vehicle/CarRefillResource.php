<?php

namespace App\Http\Resources\Api\Vehicle;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Vehicle\CarRefill
 */
class CarRefillResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $kmDriven = (float) $this->km_driven;
        $amount   = (float) $this->amount;
        $kmCost   = ($kmDriven > 0) ? round($amount / $kmDriven, 4) : null;

        return [
            'id'                => $this->id,
            'date'              => $this->date->format('Y-m-d H:i:s'),
            'code'              => $this->code,
            'filling_auth_code' => $this->filling_auth_code,
            'car_id'         => $this->car_id,
            'gas_station_id' => $this->gas_station_id,
            'driver_id'      => $this->driver_id,
            'odometer'       => number_format($this->odometer, 2, '.', ''),
            'km_driven'      => number_format($this->km_driven, 2, '.', ''),
            'amount'         => number_format($this->amount, 4, '.', ''),
            'amount_usd'     => number_format($this->amount_usd, 4, '.', ''),
            'currency_id'    => $this->currency_id,
            'currency_rate'  => number_format($this->currency_rate , 4, '.', ''),
            'km_cost'        => $kmCost,
            'invoices_count'    => $this->invoices_count,
            'sales_amount_usd'  => number_format($this->sales_amount_usd ?? 0, 4, '.', ''),
            'delivery_cost_pct' => number_format($this->delivery_cost_pct ?? 0, 2, '.', ''),
            'note'              => $this->note,
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
