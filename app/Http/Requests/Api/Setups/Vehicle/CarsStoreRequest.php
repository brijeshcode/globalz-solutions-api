<?php

namespace App\Http\Requests\Api\Setups\Vehicle;

use Illuminate\Foundation\Http\FormRequest;

class CarsStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name'         => 'required|string|max:200',
            'plate_number' => 'nullable|string|max:50',
            'year'         => 'nullable|integer|min:1990|max:' . (date('Y') + 1),
            'color'        => 'nullable|string|max:50',
            'make'         => 'nullable|string|max:100',
            'model'        => 'nullable|string|max:100',
            'note'         => 'nullable|string',
            'is_active'    => 'boolean',
        ];
    }
}
