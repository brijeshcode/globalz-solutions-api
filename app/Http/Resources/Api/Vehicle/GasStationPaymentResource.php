<?php

namespace App\Http\Resources\Api\Vehicle;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Vehicle\GasStationPayment
 */
class GasStationPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'date'           => $this->date->format('Y-m-d H:i:s'),
            'code'           => $this->code,
            'gas_station_id' => $this->gas_station_id,
            'account_id'     => $this->account_id,
            'amount'         => number_format($this->amount, 4, '.', ''),
            'amount_usd'     => number_format($this->amount_usd, 4, '.', ''),
            'currency_id'    => $this->currency_id,
            'currency_rate'  => number_format($this->currency_rate, 4, '.', ''),
            'note'           => $this->note,
            'gas_station' => $this->whenLoaded('gasStation', fn() => [
                'id'   => $this->gasStation->id,
                'name' => $this->gasStation->name,
            ]),
            'account' => $this->whenLoaded('account', fn() => [
                'id'   => $this->account->id,
                'name' => $this->account->name,
            ]),
            'created_by' => ['id' => $this->createdBy?->id, 'name' => $this->createdBy?->name],
            'updated_by' => ['id' => $this->updatedBy?->id, 'name' => $this->updatedBy?->name],
            'created_at' => $this->created_at?->format('Y-m-d H:i:s'),
            'updated_at' => $this->updated_at?->format('Y-m-d H:i:s'),
        ];
    }
}
