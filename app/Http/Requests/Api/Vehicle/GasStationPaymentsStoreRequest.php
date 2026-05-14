<?php

namespace App\Http\Requests\Api\Vehicle;

use App\Helpers\RoleHelper;
use Illuminate\Foundation\Http\FormRequest;

class GasStationPaymentsStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return RoleHelper::canWarehouseManager();
    }

    public function rules(): array
    {
        return [
            'date'           => 'required|date',
            'gas_station_id' => 'required|integer|exists:gas_stations,id',
            'account_id'     => 'required|integer|exists:accounts,id',
            'amount'         => 'required|numeric|min:0',
            'amount_usd'     => 'nullable|numeric|min:0',
            'currency_id'    => 'nullable|integer|exists:currencies,id',
            'currency_rate'  => 'nullable|numeric|min:0',
            'note'           => 'nullable|string',
        ];
    }
}
