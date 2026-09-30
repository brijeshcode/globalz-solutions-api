<?php

namespace App\Http\Requests\Api\Services;

use Illuminate\Foundation\Http\FormRequest;

class ServiceUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'sometimes|required|string|max:200',
            'tax_code_id' => 'sometimes|required|exists:tax_codes,id',
            'amount' => 'sometimes|required|numeric|min:0',
            'currency_id' => 'nullable|exists:currencies,id',
            'currency_rate' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
        ];
    }
}
