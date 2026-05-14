<?php

namespace App\Http\Requests\Api\Vehicle;

use App\Helpers\RoleHelper;
use Illuminate\Foundation\Http\FormRequest;

class GasStationsStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return RoleHelper::canAdmin();
    }

    public function rules(): array
    {
        return [
            'name'      => 'required|string|max:200|unique:gas_stations,name',
            'address'   => 'nullable|string',
            'note'      => 'nullable|string',
            'is_active' => 'boolean',
        ];
    }
}
