<?php

namespace App\Http\Requests\Api\Setups\Vehicle;

use Illuminate\Foundation\Http\FormRequest;

class CarRefillsStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'date'           => 'required|date',
            'car_id'         => 'required|integer|exists:cars,id',
            'gas_station_id' => 'required|integer|exists:gas_stations,id',
            'driver_id'      => 'required|integer|exists:employees,id',
            'odometer'       => 'required|numeric|min:0',
            'amount'         => 'required|numeric|min:0',
            'amount_usd'     => 'nullable|numeric|min:0',
            'currency_id'    => 'nullable|integer|exists:currencies,id',
            'currency_rate'  => 'nullable|numeric|min:0',
            'invoices_count' => 'nullable|integer|min:0',
            'note'           => 'nullable|string',
        ];
    }
}
