<?php

namespace App\Http\Requests\Api\Vehicle;

use App\Helpers\RoleHelper;
use Illuminate\Foundation\Http\FormRequest;

class GasStationsUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return RoleHelper::canAdmin();
    }

    public function rules(): array
    {
        $id = $this->route('gasStation')?->id;

        return [
            'name'      => "required|string|max:200|unique:gas_stations,name,{$id}",
            'address'   => 'nullable|string',
            'note'      => 'nullable|string',
            'is_active' => 'boolean',
        ];
    }
}
