<?php

namespace App\Http\Requests\Api\Setups\Vehicle;

use Illuminate\Foundation\Http\FormRequest;

class GasStationsStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'    => 'required|string|max:200|unique:gas_stations,name',
            'address' => 'required|string',
            'note'    => 'nullable|string',
        ];
    }
}
