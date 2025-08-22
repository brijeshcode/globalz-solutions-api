<?php

namespace App\Http\Requests\Api\Setups;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TaxCodeUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $taxCodeId = $this->route('tax_code')?->id ?? $this->route('taxCode')?->id;

        return [
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('tax_codes', 'code')->ignore($taxCodeId),
                'regex:/^[A-Z0-9_-]+$/', // Only uppercase letters, numbers, underscore, and dash
            ],
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'description' => [
                'nullable',
                'string',
                'max:1000',
            ],
            'tax_percent' => [
                'required',
                'numeric',
                'min:0',
                'max:999.99',
                'decimal:0,2', // Up to 2 decimal places
            ],
            'type' => [
                // 'required',
                'string',
                Rule::in(['inclusive', 'exclusive']),
            ],
            'is_active' => [
                'nullable',
                'boolean',
            ],
            'is_default' => [
                'nullable',
                'boolean',
            ],
        ];
    }

    /**
     * Get custom error messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'code.required' => 'Tax code is required.',
            'code.unique' => 'This tax code already exists.',
            'code.regex' => 'Tax code can only contain uppercase letters, numbers, underscores, and dashes.',
            'name.required' => 'Tax name is required.',
            'tax_percent.required' => 'Tax percentage is required.',
            'tax_percent.min' => 'Tax percentage cannot be negative.',
            'tax_percent.max' => 'Tax percentage cannot exceed 999.99%.',
            'tax_percent.decimal' => 'Tax percentage can have maximum 2 decimal places.',
            'type.required' => 'Tax type is required.',
            'type.in' => 'Tax type must be either inclusive or exclusive.',
        ];
    }

    /**
     * Prepare the data for validation.
     */
    protected function prepareForValidation(): void
    {
        // Convert code to uppercase
        if ($this->has('code')) {
            $this->merge([
                'code' => strtoupper($this->input('code')),
            ]);
        }
    }
}