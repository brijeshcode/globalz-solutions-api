<?php

namespace App\Http\Requests\Api\Services;

use Illuminate\Foundation\Http\FormRequest;

class ServiceStoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:200',
            'hsn' => 'nullable|string|max:255',
            'tax_code_id' => 'required|exists:tax_codes,id',
            'amount' => 'required|numeric|min:0',
            'currency_id' => 'nullable|exists:currencies,id',
            'currency_rate' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Service name is required.',
            'tax_code_id.required' => 'Tax code is required.',
            'amount.required' => 'Amount is required.',
        ];
    }
}
