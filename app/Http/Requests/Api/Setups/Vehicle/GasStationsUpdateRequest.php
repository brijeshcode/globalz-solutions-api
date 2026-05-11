<?php

namespace App\Http\Requests\Api\Setups\Vehicle;

use Illuminate\Foundation\Http\FormRequest;

class GasStationsUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $id = $this->route('gasStation')?->id;

        return [
            'name'    => "required|string|max:200|unique:gas_stations,name,{$id}",
            'address' => 'required|string',
            'note'      => 'nullable|string',
            'is_active' => 'boolean',
        ];
    }
}
